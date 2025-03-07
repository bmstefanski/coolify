<x-layout>
    <div class="flex flex-col">
        <div class="flex items-center gap-2">
            <h1>Resources</h1>
            @if ($environment->can_delete_environment())
                <livewire:project.delete-environment :environment_id="$environment->id" />
            @else
                <a href="{{ route('project.resources.new', ['project_uuid' => request()->route('project_uuid'), 'environment_name' => request()->route('environment_name')]) }}  "
                    class="font-normal text-white normal-case border-none rounded hover:no-underline btn btn-primary btn-sm no-animation">+
                    New</a>
            @endif
        </div>
        <nav class="flex pt-2 pb-10">
            <ol class="flex items-center">
                <li class="inline-flex items-center">
                    <a class="text-xs truncate lg:text-sm"
                        href="{{ route('project.show', ['project_uuid' => request()->route('project_uuid')]) }}">
                        {{ $project->name }}</a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg aria-hidden="true" class="w-4 h-4 mx-1 font-bold text-warning" fill="currentColor"
                            viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd"
                                d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <a class="text-xs truncate lg:text-sm"
                            href="{{ route('project.resources', ['environment_name' => request()->route('environment_name'), 'project_uuid' => request()->route('project_uuid')]) }}">{{ request()->route('environment_name') }}</a>
                    </div>
                </li>
            </ol>
        </nav>
    </div>
    @if ($environment->can_delete_environment())
        <a href="{{ route('project.resources.new', ['project_uuid' => request()->route('project_uuid'), 'environment_name' => request()->route('environment_name')]) }}  "
            class="items-center justify-center box">+ Add New Resource</a>
    @endif
    <div class="grid gap-2 lg:grid-cols-2">
        @foreach ($environment->applications->sortBy('name') as $application)
            <a class="relative box group"
                href="{{ route('project.application.configuration', [$project->uuid, $environment->name, $application->uuid]) }}">
                <div class="flex flex-col mx-6">
                    <div class="font-bold text-white">{{ $application->name }}</div>
                    <div class="text-xs text-gray-400 group-hover:text-white">{{ $application->description }}</div>

                </div>
                @if (Str::of(data_get($application, 'status'))->startsWith('running'))
                    <div class="absolute bg-green-400 -top-1 -left-1 badge badge-xs"></div>
                @elseif (Str::of(data_get($application, 'status'))->startsWith('exited'))
                    <div class="absolute bg-error -top-1 -left-1 badge badge-xs"></div>
                @endif
            </a>
        @endforeach
        @foreach ($environment->databases()->sortBy('name') as $database)
            <a class="relative box group"
                href="{{ route('project.database.configuration', [$project->uuid, $environment->name, $database->uuid]) }}">
                <div class="flex flex-col mx-6">
                    <div class="font-bold text-white">{{ $database->name }}</div>
                    <div class="text-xs text-gray-400 group-hover:text-white">{{ $database->description }}</div>
                </div>
                @if (Str::of(data_get($database, 'status'))->startsWith('running'))
                    <div class="absolute bg-green-400 -top-1 -left-1 badge badge-xs"></div>
                @elseif (Str::of(data_get($database, 'status'))->startsWith('exited'))
                    <div class="absolute bg-error -top-1 -left-1 badge badge-xs"></div>
                @endif
            </a>
        @endforeach
        @foreach ($environment->services->sortBy('name') as $service)
            <a class="relative box group"
                href="{{ route('project.service', [$project->uuid, $environment->name, $service->uuid]) }}">
                <div class="flex flex-col mx-6">
                    <div class="font-bold text-white">{{ $service->name }}</div>
                    <div class="text-xs text-gray-400 group-hover:text-white">{{ $service->description }}</div>
                </div>
                @if (Str::of(serviceStatus($service))->startsWith('running'))
                    <div class="absolute bg-green-400 -top-1 -left-1 badge badge-xs"></div>
                @elseif (Str::of(serviceStatus($service))->startsWith('degraded'))
                    <div class="absolute bg-yellow-400 -top-1 -left-1 badge badge-xs"></div>
                @elseif (Str::of(serviceStatus($service))->startsWith('exited'))
                    <div class="absolute bg-error -top-1 -left-1 badge badge-xs"></div>
                @endif
            </a>
        @endforeach
    </div>
</x-layout>
