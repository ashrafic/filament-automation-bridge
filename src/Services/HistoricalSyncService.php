<?php

namespace Ashrafic\FilamentWebhookBridge\Services;

use Ashrafic\FilamentWebhookBridge\Enums\DeliverySource;
use Ashrafic\FilamentWebhookBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Jobs\ProcessHistoricalSyncBatch;
use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HistoricalSyncService
{
    public function __construct(
        private PayloadBuilder $payloadBuilder,
        private ConditionEvaluator $conditionEvaluator,
    ) {}

    public function startSync(WebhookTrigger $trigger, bool $applyConditions = true, int $batchSize = 100, int $delaySeconds = 1): string
    {
        $maxBatchSize = config('filament-webhook-bridge.historical_sync.max_batch_size', 1000);
        $configuredBatchSize = config('filament-webhook-bridge.historical_sync.batch_size', 100);
        $configuredDelay = config('filament-webhook-bridge.historical_sync.batch_delay_seconds', 1);

        if ($batchSize <= 0) {
            $batchSize = $configuredBatchSize;
        }

        if ($batchSize > $maxBatchSize) {
            $batchSize = $maxBatchSize;
        }

        if ($delaySeconds < 0) {
            $delaySeconds = $configuredDelay;
        }

        $modelClass = $trigger->model_class;

        if (! class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class [{$modelClass}] does not exist.");
        }

        $lockKey = "webhook_bridge.sync.lock.{$trigger->id}";

        if (Cache::lock($lockKey, 300)->get()) {
            try {
                return $this->executeSync($trigger, $applyConditions, $batchSize, $delaySeconds, $modelClass);
            } finally {
                Cache::lock($lockKey)->release();
            }
        }

        throw new \RuntimeException("A historical sync is already in progress for trigger ID {$trigger->id}.");
    }

    public function getProgress(string $batchUuid): array
    {
        $data = Cache::get("webhook_bridge.sync.{$batchUuid}");

        if ($data === null) {
            return [
                'total' => 0,
                'processed' => 0,
                'successful' => 0,
                'failed' => 0,
                'eta_seconds' => 0,
                'status' => 'not_found',
            ];
        }

        $remaining = $data['total'] - $data['processed'];
        $delaySeconds = config('filament-webhook-bridge.historical_sync.batch_delay_seconds', 1);
        $etaSeconds = $remaining > 0 ? (int) ceil($remaining / config('filament-webhook-bridge.historical_sync.batch_size', 100)) * $delaySeconds : 0;

        return [
            'total' => $data['total'],
            'processed' => $data['processed'],
            'successful' => $data['successful'],
            'failed' => $data['failed'],
            'eta_seconds' => $etaSeconds,
            'status' => $data['status'],
        ];
    }

    public function cancelSync(string $batchUuid): bool
    {
        $data = Cache::get("webhook_bridge.sync.{$batchUuid}");

        if ($data === null) {
            return false;
        }

        if (in_array($data['status'], ['completed', 'cancelled'])) {
            return false;
        }

        $data['status'] = 'cancelled';
        Cache::put("webhook_bridge.sync.{$batchUuid}", $data, now()->addDays(7));

        Log::info('Historical sync cancelled', [
            'batch_uuid' => $batchUuid,
            'trigger_id' => $data['trigger_id'] ?? null,
        ]);

        return true;
    }

    public function processBatch(string $batchUuid, WebhookTrigger $trigger, array $modelIds, bool $applyConditions): array
    {
        $data = Cache::get("webhook_bridge.sync.{$batchUuid}");

        if ($data === null) {
            return ['processed' => 0, 'successful' => 0, 'failed' => 0];
        }

        if (($data['status'] ?? null) === 'cancelled') {
            return ['processed' => 0, 'successful' => 0, 'failed' => 0];
        }

        $freshTrigger = WebhookTrigger::find($trigger->id);

        if ($freshTrigger === null || ! $freshTrigger->active) {
            $this->markSyncCompleted($batchUuid, $data, 'cancelled');

            return ['processed' => 0, 'successful' => 0, 'failed' => 0];
        }

        $trigger = $freshTrigger;

        if ($trigger->event === EventEnum::Deleted) {
            Log::warning('Historical sync attempted for deleted event — not supported', [
                'trigger_id' => $trigger->id,
                'batch_uuid' => $batchUuid,
            ]);

            $this->markSyncCompleted($batchUuid, $data, 'completed');

            return ['processed' => count($modelIds), 'successful' => 0, 'failed' => count($modelIds)];
        }

        $modelClass = $trigger->model_class;

        if (! class_exists($modelClass)) {
            return ['processed' => 0, 'successful' => 0, 'failed' => 0];
        }

        $eagerLoad = $this->extractEagerLoadRelations($trigger);

        $models = $modelClass::with($eagerLoad)
            ->whereKey($modelIds)
            ->get();

        $processed = 0;
        $successful = 0;
        $failed = 0;

        foreach ($models as $model) {
            try {
                $currentData = Cache::get("webhook_bridge.sync.{$batchUuid}");

                if ($currentData !== null && ($currentData['status'] ?? null) === 'cancelled') {
                    break;
                }

                if ($applyConditions && ! empty($trigger->conditions)) {
                    $original = ($trigger->event === EventEnum::Updated)
                        ? []
                        : [];

                    $passes = $this->conditionEvaluator->evaluate($model, $trigger->conditions, $original);

                    if (! $passes) {
                        $processed++;
                        $this->incrementProgress($batchUuid, 'processed');

                        continue;
                    }
                }

                $payload = $this->payloadBuilder->build($trigger, $model);

                WebhookDelivery::create([
                    'trigger_id' => $trigger->id,
                    'model_type' => get_class($model),
                    'model_id' => $model->getKey(),
                    'payload' => $payload,
                    'status' => DeliveryStatus::Pending,
                    'source' => DeliverySource::HistoricalSync,
                    'max_retries' => $trigger->max_retries,
                    'dispatched_at' => now(),
                ]);

                $successful++;
                $this->incrementProgress($batchUuid, 'successful');
            } catch (\Throwable $e) {
                Log::error('Historical sync failed for model', [
                    'batch_uuid' => $batchUuid,
                    'trigger_id' => $trigger->id,
                    'model_id' => $model->getKey(),
                    'error' => $e->getMessage(),
                ]);

                $failed++;
                $this->incrementProgress($batchUuid, 'failed');
            } finally {
                $processed++;
                $this->incrementProgress($batchUuid, 'processed');
            }
        }

        $this->checkAndMarkCompleted($batchUuid);

        return [
            'processed' => $processed,
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    protected function executeSync(WebhookTrigger $trigger, bool $applyConditions, int $batchSize, int $delaySeconds, string $modelClass): string
    {
        $batchUuid = Str::uuid()->toString();

        $total = $modelClass::count();

        Cache::put("webhook_bridge.sync.{$batchUuid}", [
            'status' => 'in_progress',
            'total' => $total,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'trigger_id' => $trigger->id,
            'apply_conditions' => $applyConditions,
        ], now()->addDays(7));

        if ($total === 0) {
            $this->markSyncCompleted($batchUuid, Cache::get("webhook_bridge.sync.{$batchUuid}"), 'completed');

            return $batchUuid;
        }

        $modelClass::chunkById($batchSize, function ($models) use ($batchUuid, $trigger, $applyConditions, $delaySeconds) {
            $modelIds = $models->pluck($models->first()->getKeyName())->toArray();

            ProcessHistoricalSyncBatch::dispatch(
                $batchUuid,
                $trigger->id,
                $modelIds,
                $applyConditions,
            )->delay(now()->addSeconds($delaySeconds));
        });

        return $batchUuid;
    }

    protected function extractEagerLoadRelations(WebhookTrigger $trigger): array
    {
        $fieldMapping = $trigger->field_mapping ?? [];

        if (empty($fieldMapping)) {
            return [];
        }

        $relations = [];

        foreach ($fieldMapping as $fieldPath) {
            $fieldPath = (string) $fieldPath;

            if (! str_contains($fieldPath, '.')) {
                continue;
            }

            $segments = explode('.', $fieldPath);
            $relationParts = [];

            for ($i = 0; $i < count($segments) - 1; $i++) {
                $segment = $segments[$i];

                if ($segment === '*') {
                    break;
                }

                $relationParts[] = $segment;
            }

            if (! empty($relationParts)) {
                $relationPath = implode('.', $relationParts);

                if (! in_array($relationPath, $relations)) {
                    $relations[] = $relationPath;
                }
            }
        }

        return $relations;
    }

    protected function incrementProgress(string $batchUuid, string $field): void
    {
        $lock = Cache::lock("webhook_bridge.sync.progress_lock.{$batchUuid}", 10);

        try {
            $lock->block(5);

            $data = Cache::get("webhook_bridge.sync.{$batchUuid}");

            if ($data !== null) {
                $data[$field] = ($data[$field] ?? 0) + 1;
                Cache::put("webhook_bridge.sync.{$batchUuid}", $data, now()->addDays(7));
            }
        } finally {
            optional($lock)->release();
        }
    }

    protected function checkAndMarkCompleted(string $batchUuid): void
    {
        $data = Cache::get("webhook_bridge.sync.{$batchUuid}");

        if ($data === null) {
            return;
        }

        if ($data['processed'] >= $data['total']) {
            $this->markSyncCompleted($batchUuid, $data, 'completed');
        }
    }

    protected function markSyncCompleted(string $batchUuid, array $data, string $status): void
    {
        $data['status'] = $status;
        Cache::put("webhook_bridge.sync.{$batchUuid}", $data, now()->addDays(7));
    }
}