<?php

namespace Ashrafic\FilamentWebhookBridge\Commands;

use Ashrafic\FilamentWebhookBridge\Services\ModelDiscoveryService;
use Illuminate\Console\Command;

class ModelCacheCommand extends Command
{
    protected $signature = 'webhook-bridge:model-cache';

    protected $description = 'Clear and rebuild the model discovery cache';

    public function handle(): int
    {
        $this->info('Clearing model discovery cache...');

        $service = app(ModelDiscoveryService::class);

        $service->refreshCache();

        $models = $service->getAllModels();

        $count = count($models);

        $this->info("Model discovery cache rebuilt. {$count} model(s) discovered.");

        if ($this->output->isVerbose()) {
            foreach ($models as $fqcn => $basename) {
                $this->line("  - {$fqcn}");
            }
        }

        return self::SUCCESS;
    }
}