<?php

namespace Ashrafic\FilamentWebhookBridge;

use Ashrafic\FilamentWebhookBridge\Commands\InstallCommand;
use Ashrafic\FilamentWebhookBridge\Commands\ModelCacheCommand;
use Ashrafic\FilamentWebhookBridge\Commands\PruneDeliveryLogsCommand;
use Ashrafic\FilamentWebhookBridge\Commands\SyncHistoricalRecordsCommand;
use Ashrafic\FilamentWebhookBridge\Commands\TestConnectionCommand;
use Ashrafic\FilamentWebhookBridge\Conditions\ConditionRegistry;
use Ashrafic\FilamentWebhookBridge\Formatters\CustomFormatter;
use Ashrafic\FilamentWebhookBridge\Formatters\MakeFormatter;
use Ashrafic\FilamentWebhookBridge\Formatters\N8nFormatter;
use Ashrafic\FilamentWebhookBridge\Formatters\ZapierFormatter;
use Ashrafic\FilamentWebhookBridge\Listeners\WebhookEventSubscriber;
use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Services\DeliveryService;
use Ashrafic\FilamentWebhookBridge\Triggers\TriggerManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;

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
                'add_trigger_type_to_webhook_triggers_table',
            ])
            ->hasCommands([
                InstallCommand::class,
                PruneDeliveryLogsCommand::class,
                ModelCacheCommand::class,
                SyncHistoricalRecordsCommand::class,
                TestConnectionCommand::class,
            ])
            ->hasViews()
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        parent::packageRegistered();

        $this->app->singleton(ConditionRegistry::class);

        $this->app->singleton(TriggerManager::class);

        $this->app->singleton('webhook-bridge', DeliveryService::class);

        $this->app->tag([
            ZapierFormatter::class,
            MakeFormatter::class,
            N8nFormatter::class,
            CustomFormatter::class,
        ], 'webhook-bridge.formatters');
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        if (config('filament-webhook-bridge.models.paths')) {
            Event::listen('eloquent.*', [WebhookEventSubscriber::class, 'handle']);
        }

        if (config('filament-webhook-bridge.retention.prune_enabled', true)) {
            $this->app->make(Schedule::class)
                ->command('webhook-bridge:prune-logs')
                ->daily();
        }

        if (class_exists(WebhookCallSucceededEvent::class)) {
            Event::listen(WebhookCallSucceededEvent::class, function (WebhookCallSucceededEvent $event) {
                $delivery = WebhookDelivery::where('uuid', $event->uuid ?? '')->first();

                if ($delivery) {
                    app(DeliveryService::class)->handleSpatieSuccess($delivery, $event->response);
                }
            });

            Event::listen(WebhookCallFailedEvent::class, function (WebhookCallFailedEvent $event) {
                $delivery = WebhookDelivery::where('uuid', $event->uuid ?? '')->first();

                if ($delivery) {
                    app(DeliveryService::class)->handleSpatieFailure($delivery, new \RuntimeException($event->errorMessage ?? 'Webhook call failed'));
                }
            });
        }
    }
}
