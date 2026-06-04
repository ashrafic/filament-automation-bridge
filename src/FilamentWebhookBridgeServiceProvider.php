<?php

namespace Ashrafic\FilamentWebhookBridge;

use Ashrafic\FilamentWebhookBridge\Commands\InstallCommand;
use Ashrafic\FilamentWebhookBridge\Commands\ModelCacheCommand;
use Ashrafic\FilamentWebhookBridge\Commands\PruneDeliveryLogsCommand;
use Ashrafic\FilamentWebhookBridge\Commands\SyncHistoricalRecordsCommand;
use Ashrafic\FilamentWebhookBridge\Commands\TestConnectionCommand;
use Ashrafic\FilamentWebhookBridge\Listeners\WebhookEventSubscriber;
use Ashrafic\FilamentWebhookBridge\Services\DeliveryService;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentWebhookBridgeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-webhook-bridge')
            ->hasConfigFile()
            ->hasMigrations([
                'create_webhook_triggers_table',
                'create_webhook_deliveries_table',
                'create_webhook_templates_table',
            ])
            ->hasCommands([
                InstallCommand::class,
                PruneDeliveryLogsCommand::class,
                ModelCacheCommand::class,
                SyncHistoricalRecordsCommand::class,
                TestConnectionCommand::class,
            ])
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        $this->app->singleton('webhook-bridge', DeliveryService::class);

        if (config('filament-webhook-bridge.models.paths')) {
            Event::subscribe(WebhookEventSubscriber::class);
        }

        if (config('filament-webhook-bridge.retention.prune_enabled', true)) {
            $this->app->make(\Illuminate\Console\Scheduling\Schedule::class)
                ->command('webhook-bridge:prune-logs')
                ->daily();
        }
    }
}