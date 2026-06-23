<?php

namespace Ashrafic\FilamentAutomationBridge\Jobs;

use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\HistoricalSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessHistoricalSyncBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $batchUuid,
        public int $triggerId,
        public array $modelIds,
        public string $modelClass,
        public bool $applyConditions,
    ) {
        $this->onQueue(config('filament-automation-bridge.queue.historical_sync_queue_name', 'webhooks-sync'));
    }

    public function handle(HistoricalSyncService $syncService): void
    {
        $data = Cache::get("automation_bridge.sync.{$this->batchUuid}");

        if ($data === null || ($data['status'] ?? null) === 'cancelled') {
            return;
        }

        $trigger = AutomationTrigger::find($this->triggerId);

        if ($trigger === null || ! $trigger->active) {
            Log::warning('ProcessHistoricalSyncBatch: trigger not found or inactive', [
                'batch_uuid' => $this->batchUuid,
                'trigger_id' => $this->triggerId,
            ]);

            return;
        }

        $syncService->processBatch(
            $this->batchUuid,
            $trigger,
            $this->modelIds,
            $this->applyConditions,
        );
    }

    public function tags(): array
    {
        return ['automation-bridge', 'sync:'.$this->batchUuid];
    }
}
