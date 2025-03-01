<div x-init="$wire.getLogs">
    <div class="flex gap-2">
        <h2>Logs</h2>
        @if ($streamLogs)
            <span wire:poll.2000ms='getLogs(true)' class="loading loading-xs text-warning loading-spinner"></span>
        @endif
    </div>
    <form wire:submit.prevent='getLogs(true)' class="flex items-end gap-2">
        <x-forms.input label="Only Show Number of Lines" placeholder="1000" required id="numberOfLines"></x-forms.input>
        <x-forms.button type="submit">Refresh</x-forms.button>
    </form>
    <div class="w-32">
        <x-forms.checkbox instantSave label="Stream Logs" id="streamLogs"></x-forms.checkbox>
    </div>
    <div class="container w-full pt-4 mx-auto">
        <div
            class="scrollbar flex flex-col-reverse w-full overflow-y-auto border border-solid rounded border-coolgray-300 max-h-[32rem] p-4 pt-6 text-xs text-white">

            <pre class="font-mono whitespace-pre-wrap">{{ $outputs }}</pre>
        </div>
    </div>
</div>
