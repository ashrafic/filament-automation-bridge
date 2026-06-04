<?php

namespace Ashrafic\FilamentWebhookBridge\Commands;

use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\HistoricalSyncService;
use Illuminate\Console\Command;

class SyncHistoricalRecordsCommand extends Command
{
    protected $signature = 'webhook-bridge:sync
        {triggerId : The ID of the trigger to sync}
        {--batch= : Batch size (default from config)}
        {--no-conditions : Skip condition evaluation}';

    protected $description = 'Start a historical sync for a webhook trigger';

    public function handle(): int
    {
        $triggerId = $this->argument('triggerId');

        $trigger = WebhookTrigger::find($triggerId);

        if (! $trigger) {
            $this->error("Webhook trigger with ID {$triggerId} not found.");

            return self::FAILURE;
        }

        if (! $trigger->active) {
            $this->error("Webhook trigger [{$trigger->name}] (ID: {$triggerId}) is not active.");

            return self::FAILURE;
        }

        $batchSize = $this->option('batch')
            ? (int) $this->option('batch')
            : config('filament-webhook-bridge.historical_sync.batch_size', 100);

        $applyConditions = ! $this->option('no-conditions');

        $this->info("Starting historical sync for trigger [{$trigger->name}] (ID: {$triggerId})...");
        $this->line("  Model: {$trigger->model_class}");
        $this->line("  Event: {$trigger->event->getLabel()}");
        $this->line("  Batch size: {$batchSize}");
        $this->line('  Apply conditions: '.($applyConditions ? 'Yes' : 'No'));

        try {
            $batchUuid = app(HistoricalSyncService::class)->startSync(
                trigger: $trigger,
                applyConditions: $applyConditions,
                batchSize: $batchSize,
            );

            $this->newLine();
            $this->info("Historical sync started. Batch UUID: {$batchUuid}");
            $this->line('  Use `php artisan webhook-bridge:sync-progress '.$batchUuid.'` to check progress.');
        } catch (\Throwable $e) {
            $this->error("Failed to start sync: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
