<?php

namespace Ashrafic\FilamentWebhookBridge\Jobs;

use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\HistoricalSyncService;
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

    public string $queue;

    public function __construct(
        public string $batchUuid,
        public int $triggerId,
        public array $modelIds,
        public string $modelClass,
        public bool $applyConditions,
    ) {
        $this->queue = config('filament-webhook-bridge.queue.historical_sync_queue_name', 'webhooks-sync');
    }

    public function handle(HistoricalSyncService $syncService): void
    {
        $data = Cache::get("webhook_bridge.sync.{$this->batchUuid}");

        if ($data === null || ($data['status'] ?? null) === 'cancelled') {
            return;
        }

        $trigger = WebhookTrigger::find($this->triggerId);

        if ($trigger === null || !$trigger->active) {
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
        return ['webhook-bridge', 'sync:' . $this->batchUuid];
    }
}