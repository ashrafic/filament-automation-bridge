<div
    x-data="{
        copied: false,
        copy() {
            navigator.clipboard.writeText($refs.jsonContent.textContent);
            this.copied = true;
            setTimeout(() => { this.copied = false; }, 2000);
        }
    }"
    class="space-y-2"
>
    @if($destination)
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Destination format: {{ $destination }}
            </p>
            <button
                type="button"
                x-on:click="copy"
                class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
            >
                <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <svg x-show="copied" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span x-text="copied ? 'Copied!' : 'Copy'"></span>
            </button>
        </div>
    @endif

    <pre
        x-ref="jsonContent"
        class="max-h-96 overflow-auto rounded-lg bg-gray-900 p-4 text-xs leading-relaxed text-green-400 dark:bg-gray-950"
    ><code>{{ $json }}</code></pre>
</div>