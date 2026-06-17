<div class="space-y-4">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-filament::section>
            <x-slot name="heading">Active Triggers</x-slot>
            <div class="text-3xl font-bold text-success-500">{{ $activeTriggers }}</div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Deliveries (24h)</x-slot>
            <div class="text-3xl font-bold text-primary-500">{{ $deliveries24h }}</div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Success Rate</x-slot>
            <div class="text-3xl font-bold {{ $successRate !== null && $successRate >= 90 ? 'text-success-500' : ($successRate !== null && $successRate >= 70 ? 'text-warning-500' : 'text-danger-500') }}">
                {{ $successRate !== null ? $successRate . '%' : 'N/A' }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Failed (Needs Attention)</x-slot>
            <div class="text-3xl font-bold {{ $failedNeedsAttention > 0 ? 'text-danger-500' : 'text-success-500' }}">
                {{ $failedNeedsAttention }}
            </div>
        </x-filament::section>
    </div>

    @if($recentFailures->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Recent Failures</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Trigger</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Model</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">HTTP Status</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Error</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Time</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentFailures as $failure)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2">{{ $failure->trigger?->name ?? 'N/A' }}</td>
                                <td class="px-3 py-2">{{ class_basename($failure->model_type) }} #{{ $failure->model_id }}</td>
                                <td class="px-3 py-2">{{ $failure->http_status ?? '—' }}</td>
                                <td class="px-3 py-2 max-w-xs truncate">{{ $failure->error_message ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $failure->created_at?->diffForHumans() }}</td>
                                <td class="px-3 py-2">
                                    @if($failure->canRetry())
                                        <x-filament::button
                                            size="xs"
                                            color="warning"
                                            icon="heroicon-o-arrow-path"
                                            wire:click="retryDelivery({{ $failure->id }})"
                                        >
                                            Retry
                                        </x-filament::button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</div>