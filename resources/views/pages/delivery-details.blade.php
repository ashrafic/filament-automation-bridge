<div class="space-y-6" x-on:click.stop="">
    @if($record->payload)
        <x-filament::section>
            <x-slot name="heading">Request Payload</x-slot>
            <div x-data="{ copied: false }" class="relative">
                <pre
                    x-ref="code"
                    class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-4 pr-20 text-xs leading-relaxed whitespace-pre-wrap break-all text-green-400 dark:bg-gray-950"
                >{{ json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                <button
                    type="button"
                    x-on:click="navigator.clipboard.writeText($refs.code.textContent).then(() => { copied = true; setTimeout(() => copied = false, 2000) }).catch(() => {})"
                    class="absolute top-2 right-2 flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium bg-gray-700 text-gray-200 hover:bg-gray-600 transition"
                >
                    <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                    <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
            </div>
        </x-filament::section>
    @endif

    @if($record->headers)
        <x-filament::section>
            <x-slot name="heading">Request Headers</x-slot>
            <div x-data="{ copied: false }" class="relative">
                <pre
                    x-ref="code"
                    class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-4 pr-20 text-xs leading-relaxed whitespace-pre-wrap break-all text-green-400 dark:bg-gray-950"
                >{{ json_encode($record->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                <button
                    type="button"
                    x-on:click="navigator.clipboard.writeText($refs.code.textContent).then(() => { copied = true; setTimeout(() => copied = false, 2000) }).catch(() => {})"
                    class="absolute top-2 right-2 flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium bg-gray-700 text-gray-200 hover:bg-gray-600 transition"
                >
                    <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                    <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
            </div>
        </x-filament::section>
    @endif

    @if($record->response_body)
        <x-filament::section>
            <x-slot name="heading">Response Body</x-slot>
            <div x-data="{ copied: false }" class="relative">
                <pre
                    x-ref="code"
                    class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-4 pr-20 text-xs leading-relaxed whitespace-pre-wrap break-all text-green-400 dark:bg-gray-950"
                >{{ is_string($record->response_body) ? $record->response_body : json_encode($record->response_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                <button
                    type="button"
                    x-on:click="navigator.clipboard.writeText($refs.code.textContent).then(() => { copied = true; setTimeout(() => copied = false, 2000) }).catch(() => {})"
                    class="absolute top-2 right-2 flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium bg-gray-700 text-gray-200 hover:bg-gray-600 transition"
                >
                    <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                    <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
            </div>
        </x-filament::section>
    @endif

    @if($record->response_headers)
        <x-filament::section>
            <x-slot name="heading">Response Headers</x-slot>
            <div x-data="{ copied: false }" class="relative">
                <pre
                    x-ref="code"
                    class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-4 pr-20 text-xs leading-relaxed whitespace-pre-wrap break-all text-green-400 dark:bg-gray-950"
                >{{ json_encode($record->response_headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                <button
                    type="button"
                    x-on:click="navigator.clipboard.writeText($refs.code.textContent).then(() => { copied = true; setTimeout(() => copied = false, 2000) }).catch(() => {})"
                    class="absolute top-2 right-2 flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium bg-gray-700 text-gray-200 hover:bg-gray-600 transition"
                >
                    <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                    <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
            </div>
        </x-filament::section>
    @endif

    @if($record->error_message)
        <x-filament::section>
            <x-slot name="heading">Error</x-slot>
            <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                {{ $record->error_message }}
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">Delivery Details</x-slot>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-medium text-gray-500 dark:text-gray-400">Trigger:</span>
                {{ $record->trigger?->name ?? 'N/A' }}
            </div>
            <div>
                <span class="font-medium text-gray-500 dark:text-gray-400">Model:</span>
                {{ class_basename($record->model_type) }} #{{ $record->model_id }}
            </div>
            <div>
                <span class="font-medium text-gray-500 dark:text-gray-400">Status:</span>
                {{ $record->status->getLabel() }}
            </div>
            <div>
                <span class="font-medium text-gray-500 dark:text-gray-400">HTTP Status:</span>
                {{ $record->http_status ?? 'N/A' }}
            </div>
            <div>
                <span class="font-medium text-gray-500 dark:text-gray-400">Retries:</span>
                {{ $record->retry_count }} / {{ $record->max_retries }}
            </div>
            <div>
                <span class="font-medium text-gray-500 dark:text-gray-400">Duration:</span>
                {{ $record->duration_ms ? $record->duration_ms . ' ms' : 'N/A' }}
            </div>
            <div>
                <span class="font-medium text-gray-500 dark:text-gray-400">Source:</span>
                {{ $record->source->getLabel() }}
            </div>
            <div>
                <span class="font-medium text-gray-500 dark:text-gray-400">Dispatched:</span>
                {{ $record->dispatched_at?->format('M d, Y H:i:s') ?? 'N/A' }}
            </div>
            <div>
                <span class="font-medium text-gray-500 dark:text-gray-400">Completed:</span>
                {{ $record->completed_at?->format('M d, Y H:i:s') ?? 'N/A' }}
            </div>
            <div>
                <span class="font-medium text-gray-500 dark:text-gray-400">UUID:</span>
                {{ $record->uuid }}
            </div>
        </div>
    </x-filament::section>
</div>
