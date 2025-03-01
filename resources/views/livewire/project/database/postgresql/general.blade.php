<div>
    <dialog id="newInitScript" class="modal">
        <form method="dialog" class="flex flex-col gap-2 rounded modal-box" wire:submit.prevent='save_new_init_script'>
            <h3 class="text-lg font-bold">Add Init Script</h3>
            <x-forms.input placeholder="create_test_db.sql" id="new_filename" label="Filename" required />
            <x-forms.textarea placeholder="CREATE DATABASE test;" id="new_content" label="Content" required />
            <x-forms.button onclick="newInitScript.close()" type="submit">
                Save
            </x-forms.button>
        </form>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <form wire:submit.prevent="submit" class="flex flex-col gap-2">
        <div class="flex items-center gap-2">
            <h2>General</h2>
            <x-forms.button type="submit">
                Save
            </x-forms.button>
        </div>
        <div class="flex gap-2">
            <x-forms.input label="Name" id="database.name" />
            <x-forms.input label="Description" id="database.description" />
            <x-forms.input label="Image" id="database.image" required
                helper="For all available images, check here:<br><br><a target='_blank' href='https://hub.docker.com/_/postgres'>https://hub.docker.com/_/postgres</a>" />
        </div>

        @if ($database->started_at)
            <div class="flex gap-2">
                <x-forms.input label="Initial Username" id="database.postgres_username" placeholder="If empty: postgres"
                    readonly helper="You can only change this in the database." />
                <x-forms.input label="Initial Password" id="database.postgres_password" type="password" required readonly
                    helper="You can only change this in the database." />
                <x-forms.input label="Initial Database" id="database.postgres_db"
                    placeholder="If empty, it will be the same as Username." readonly
                    helper="You can only change this in the database." />
            </div>
        @else
            <div class="pt-8 text-warning">Please verify these values. You can only modify them before the initial start. After that, you need to modify it in the database.
            </div>
            <div class="flex gap-2 pb-8">
                <x-forms.input label="Username" id="database.postgres_user" placeholder="If empty: postgres" />
                <x-forms.input label="Password" id="database.postgres_password" type="password" required />
                <x-forms.input label="Database" id="database.postgres_db"
                    placeholder="If empty, it will be the same as Username." />
            </div>
        @endif
        <div class="flex gap-2">
            <x-forms.input label="Initial Database Arguments" id="database.postgres_initdb_args"
                placeholder="If empty, use default. See in docker docs." />
            <x-forms.input label="Host Auth Method" id="database.postgres_host_auth_method"
                placeholder="If empty, use default. See in docker docs." />
        </div>
        <div class="flex flex-col gap-2">
            <h3 class="py-2">Network</h3>
            <div class="flex items-end gap-2">
                <x-forms.input placeholder="3000:5432" id="database.ports_mappings" label="Ports Mappings"
                    helper="A comma separated list of ports you would like to map to the host system.<br><span class='inline-block font-bold text-warning'>Example</span>3000:5432,3002:5433" />
                <x-forms.input placeholder="5432" disabled="{{ $database->is_public }}" id="database.public_port"
                    label="Public Port" />
                <x-forms.checkbox instantSave id="database.is_public" label="Accessible over the internet" />
            </div>
            <x-forms.input label="Postgres URL" type="password" readonly wire:model="db_url" />
        </div>
    </form>
    <div class="pb-16">
        <div class="flex gap-2 pt-4 pb-2">
            <h3>Initialization scripts</h3>
            <x-forms.button class="btn" onclick="newInitScript.showModal()">+ Add</x-forms.button>
        </div>
        <div class="flex flex-col gap-2">
            @forelse(data_get($database,'init_scripts', []) as $script)
                <livewire:project.database.init-script :script="$script" :wire:key="$script['index']" />
            @empty
                <div>No initialization scripts found.</div>
            @endforelse
        </div>
    </div>
</div>
