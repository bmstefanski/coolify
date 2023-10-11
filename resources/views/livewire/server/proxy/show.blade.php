<div>
    <x-server.navbar :server="$server" :parameters="$parameters" />
    <div class="flex gap-2">
        <x-server.sidebar :server="$server" :parameters="$parameters" />
        <div class="w-full">
            <livewire:server.proxy :server="$server" />
        </div>
    </div>
</div>
