<?php

use App\Actions\CoolifyTask\PrepareCoolifyTask;
use App\Data\CoolifyTaskArgs;
use App\Enums\ActivityTypes;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Notifications\Server\Revived;
use App\Notifications\Server\Unreachable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity;

function remote_process(
    Collection|array   $command,
    Server  $server,
    ?string  $type = null,
    ?string $type_uuid = null,
    ?Model  $model = null,
    bool    $ignore_errors = false,
): Activity {
    if (is_null($type)) {
        $type = ActivityTypes::INLINE->value;
    }
    if ($command instanceof Collection) {
        $command = $command->toArray();
    }
    $command_string = implode("\n", $command);
    if (auth()->user()) {
        $teams = auth()->user()->teams->pluck('id');
        if (!$teams->contains($server->team_id) && !$teams->contains(0)) {
            throw new \Exception("User is not part of the team that owns this server");
        }
    }
    return resolve(PrepareCoolifyTask::class, [
        'remoteProcessArgs' => new CoolifyTaskArgs(
            server_uuid: $server->uuid,
            command: <<<EOT
                {$command_string}
                EOT,
            type: $type,
            type_uuid: $type_uuid,
            model: $model,
            ignore_errors: $ignore_errors
        ),
    ])();
}

// function removePrivateKeyFromSshAgent(Server $server)
// {
//     if (data_get($server, 'privateKey.private_key') === null) {
//         throw new \Exception("Server {$server->name} does not have a private key");
//     }
//     // processWithEnv()->run("echo '{$server->privateKey->private_key}' | ssh-add -d -");
// }
function savePrivateKeyToFs(Server $server)
{
    if (data_get($server, 'privateKey.private_key') === null) {
        throw new \Exception("Server {$server->name} does not have a private key");
    }
    $sshKeyFileLocation = "id.root@{$server->uuid}";
    Storage::disk('ssh-keys')->makeDirectory('.');
    Storage::disk('ssh-mux')->makeDirectory('.');
    Storage::disk('ssh-keys')->put($sshKeyFileLocation, $server->privateKey->private_key);
    $location = '/var/www/html/storage/app/ssh/keys/' . $sshKeyFileLocation;
    return $location;
}

function generateSshCommand(Server $server, string $command, bool $isMux = true)
{
    $user = $server->user;
    $port = $server->port;
    $privateKeyLocation = savePrivateKeyToFs($server);
    $timeout = config('constants.ssh.command_timeout');
    $connectionTimeout = config('constants.ssh.connection_timeout');
    $serverInterval = config('constants.ssh.server_interval');

    $delimiter = 'EOF-COOLIFY-SSH';
    $ssh_command = "timeout $timeout ssh ";

    if ($isMux && config('coolify.mux_enabled')) {
        $ssh_command .= '-o ControlMaster=auto -o ControlPersist=1m -o ControlPath=/var/www/html/storage/app/ssh/mux/%h_%p_%r ';
    }
    if (data_get($server, 'settings.is_cloudflare_tunnel')) {
        $ssh_command .= '-o ProxyCommand="/usr/local/bin/cloudflared access ssh --hostname %h" ';
    }
    $command = "PATH=\$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/host/usr/local/sbin:/host/usr/local/bin:/host/usr/sbin:/host/usr/bin:/host/sbin:/host/bin && $command";
    $ssh_command .= "-i {$privateKeyLocation} "
        . '-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null '
        . '-o PasswordAuthentication=no '
        . "-o ConnectTimeout=$connectionTimeout "
        . "-o ServerAliveInterval=$serverInterval "
        . '-o RequestTTY=no '
        . '-o LogLevel=ERROR '
        . "-p {$port} "
        . "{$user}@{$server->ip} "
        . " 'bash -se' << \\$delimiter" . PHP_EOL
        . $command . PHP_EOL
        . $delimiter;
    // ray($ssh_command);
    return $ssh_command;
}
function instant_remote_process(Collection|array $command, Server $server, $throwError = true)
{
    $timeout = config('constants.ssh.command_timeout');
    if ($command instanceof Collection) {
        $command = $command->toArray();
    }
    $command_string = implode("\n", $command);
    $ssh_command = generateSshCommand($server, $command_string);
    $process = Process::timeout($timeout)->run($ssh_command);
    $output = trim($process->output());
    $exitCode = $process->exitCode();
    if ($exitCode !== 0) {
        if (!$throwError) {
            return null;
        }
        return excludeCertainErrors($process->errorOutput(), $exitCode);
    }
    return $output;
}
function excludeCertainErrors(string $errorOutput, ?int $exitCode = null)
{
    $ignoredErrors = collect([
        'Permission denied (publickey',
        'Could not resolve hostname',
    ]);
    $ignored = false;
    foreach ($ignoredErrors as $ignoredError) {
        if (Str::contains($errorOutput, $ignoredError)) {
            $ignored = true;
            break;
        }
    }
    if ($ignored) {
        // TODO: Create new exception and disable in sentry
        throw new \RuntimeException($errorOutput, $exitCode);
    }
    throw new \RuntimeException($errorOutput, $exitCode);
}
function decode_remote_command_output(?ApplicationDeploymentQueue $application_deployment_queue = null): Collection
{
    $application = Application::find(data_get($application_deployment_queue, 'application_id'));
    $is_debug_enabled = data_get($application, 'settings.is_debug_enabled');
    if (is_null($application_deployment_queue)) {
        return collect([]);
    }
    try {
        $decoded = json_decode(
            data_get($application_deployment_queue, 'logs'),
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );
    } catch (\JsonException $exception) {
        return collect([]);
    }
    $formatted = collect($decoded);
    if (!$is_debug_enabled) {
        $formatted = $formatted->filter(fn ($i) => $i['hidden'] === false ?? false);
    }
    $formatted = $formatted
        ->sortBy(fn ($i) => $i['order'])
        ->map(function ($i) {
            $i['timestamp'] = Carbon::parse($i['timestamp'])->format('Y-M-d H:i:s.u');
            return $i;
        });

    return $formatted;
}

function refresh_server_connection(PrivateKey $private_key)
{
    foreach ($private_key->servers as $server) {
        Storage::disk('ssh-mux')->delete($server->muxFilename());
    }
}

// function validateServer(Server $server, bool $throwError = false)
// {
//     try {
//         $uptime = instant_remote_process(['uptime'], $server, $throwError);
//         if (!$uptime) {
//             $server->settings->is_reachable = false;
//             $server->team->notify(new Unreachable($server));
//             $server->unreachable_email_sent = true;
//             $server->save();
//             return [
//                 "uptime" => null,
//                 "dockerVersion" => null,
//             ];
//         }
//         $server->settings->is_reachable = true;
//         instant_remote_process(["docker ps"], $server, $throwError);
//         $dockerVersion = instant_remote_process(["docker version|head -2|grep -i version| awk '{print $2}'"], $server, $throwError);
//         if (!$dockerVersion) {
//             $dockerVersion = null;
//             return [
//                 "uptime" => $uptime,
//                 "dockerVersion" => null,
//             ];
//         }
//         $dockerVersion = checkMinimumDockerEngineVersion($dockerVersion);
//         if (is_null($dockerVersion)) {
//             $server->settings->is_usable = false;
//         } else {
//             $server->settings->is_usable = true;
//             if (data_get($server, 'unreachable_email_sent') === true) {
//                 $server->team->notify(new Revived($server));
//                 $server->unreachable_email_sent = false;
//                 $server->save();
//             }
//         }
//         return [
//             "uptime" => $uptime,
//             "dockerVersion" => $dockerVersion,
//         ];
//     } catch (\Throwable $e) {
//         $server->settings->is_reachable = false;
//         $server->settings->is_usable = false;
//         throw $e;
//     } finally {
//         if (data_get($server, 'settings')) {
//             $server->settings->save();
//         }
//     }
// }

function checkRequiredCommands(Server $server)
{
    $commands = collect(["jq", "jc"]);
    foreach ($commands as $command) {
        $commandFound = instant_remote_process(["docker run --rm --privileged --net=host --pid=host --ipc=host --volume /:/host busybox chroot /host bash -c 'command -v {$command}'"], $server, false);
        if ($commandFound) {
            ray($command . ' found');
            continue;
        }
        try {
            instant_remote_process(["docker run --rm --privileged --net=host --pid=host --ipc=host --volume /:/host busybox chroot /host bash -c 'apt update && apt install -y {$command}'"], $server);
        } catch (\Throwable $e) {
            ray('could not install ' . $command);
            ray($e);
            break;
        }
        $commandFound = instant_remote_process(["docker run --rm --privileged --net=host --pid=host --ipc=host --volume /:/host busybox chroot /host bash -c 'command -v {$command}'"], $server, false);
        if ($commandFound) {
            ray($command . ' found');
            continue;
        }
        ray('could not install ' . $command);
        break;
    }
}
