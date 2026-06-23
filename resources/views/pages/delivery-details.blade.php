<div class="space-y-6">
    @if($record->payload)
        <x-filament::section>
            <x-slot name="heading">Request Payload</x-slot>
            <div x-data="{ copied: false }" class="relative">
                <pre
                    x-ref="code"
                    class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-4 pr-12 text-xs leading-relaxed whitespace-pre-wrap break-all text-green-400 dark:bg-gray-950"
                >{{ json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                <button
                    x-on:click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-2 right-2 rounded-lg bg-gray-700 px-2 py-1 text-xs text-gray-300 hover:bg-gray-600 transition"
                >
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied!</span>
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
                    class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-4 pr-12 text-xs leading-relaxed whitespace-pre-wrap break-all text-green-400 dark:bg-gray-950"
                >{{ json_encode($record->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                <button
                    x-on:click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-2 right-2 rounded-lg bg-gray-700 px-2 py-1 text-xs text-gray-300 hover:bg-gray-600 transition"
                >
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied!</span>
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
                    class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-4 pr-12 text-xs leading-relaxed whitespace-pre-wrap break-all text-green-400 dark:bg-gray-950"
                >{{ is_string($record->response_body) ? $record->response_body : json_encode($record->response_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                <button
                    x-on:click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-2 right-2 rounded-lg bg-gray-700 px-2 py-1 text-xs text-gray-300 hover:bg-gray-600 transition"
                >
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied!</span>
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
                    class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-4 pr-12 text-xs leading-relaxed whitespace-pre-wrap break-all text-green-400 dark:bg-gray-950"
                >{{ json_encode($record->response_headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                <button
                    x-on:click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-2 right-2 rounded-lg bg-gray-700 px-2 py-1 text-xs text-gray-300 hover:bg-gray-600 transition"
                >
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied!</span>
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
