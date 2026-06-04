<?php

namespace Ashrafic\FilamentWebhookBridge;

use Ashrafic\FilamentWebhookBridge\Commands\InstallCommand;
use Ashrafic\FilamentWebhookBridge\Commands\ModelCacheCommand;
use Ashrafic\FilamentWebhookBridge\Commands\ProcessScheduledTriggersCommand;
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
use Ashrafic\FilamentWebhookBridge\Triggers\EventTrigger;
use Ashrafic\FilamentWebhookBridge\Triggers\ManualTrigger;
use Ashrafic\FilamentWebhookBridge\Triggers\ModelEventTrigger;
use Ashrafic\FilamentWebhookBridge\Triggers\ScheduleTrigger;
use Ashrafic\FilamentWebhookBridge\Triggers\StatusChangedTrigger;
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
                ProcessScheduledTriggersCommand::class,
            ])
            ->hasViews()
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        parent::packageRegistered();

        $this->app->singleton(ConditionRegistry::class);

        $this->app->singleton(TriggerManager::class, function () {
            $manager = new TriggerManager;
            $manager->register(ModelEventTrigger::class);
            $manager->register(StatusChangedTrigger::class);
            $manager->register(ScheduleTrigger::class);
            $manager->register(ManualTrigger::class);
            $manager->register(EventTrigger::class);

            return $manager;
        });

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

        $this->app->make(Schedule::class)
            ->command('webhook-bridge:process-scheduled')
            ->everyMinute();

        if (config('filament-webhook-bridge.retention.prune_enabled', true)) {
            $this->app->make(Schedule::class)
                ->command('webhook-bridge:prune-logs')
                ->daily();
        }

        Event::listen('*', function (string $eventName, array $payload) {
            if (! \Ashrafic\FilamentWebhookBridge\Triggers\TriggerManager::hasEventSubscriptions()) {
                return;
            }

            $eventClass = $eventName;

            if (is_object($eventName)) {
                $eventClass = get_class($eventName);
            }

            $triggerIds = \Ashrafic\FilamentWebhookBridge\Triggers\TriggerManager::getTriggerIdsForEvent($eventClass);

            if (empty($triggerIds)) {
                return;
            }

            $eventObject = $payload[0] ?? null;

            if ($eventObject === null) {
                return;
            }

            $triggerManager = app(\Ashrafic\FilamentWebhookBridge\Triggers\TriggerManager::class);
            $deliveryService = app(DeliveryService::class);

            $properties = [];
            $reflection = new \ReflectionClass($eventObject);
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                if ($prop->isStatic()) {
                    continue;
                }

                $properties[$prop->getName()] = $prop->isInitialized($eventObject) ? $prop->getValue($eventObject) : null;
            }

            $model = null;
            foreach ($properties as $value) {
                if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                    $model = $value;
                    break;
                }
            }

            foreach ($triggerIds as $triggerId) {
                try {
                    $trigger = \Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger::find($triggerId);

                    if ($trigger === null || ! $trigger->active) {
                        continue;
                    }

                    if ($model === null && class_exists($trigger->model_class)) {
                        $modelClass = $trigger->model_class;
                        $model = $modelClass::query()->latest()->first();
                    }

                    if ($model === null) {
                        continue;
                    }

                    $triggerInstance = $triggerManager->get($trigger->trigger_type);

                    if (! $triggerInstance->shouldFire($model, $trigger->trigger_config ?? [], [
                        'event_class' => $eventClass,
                        'event_properties' => $properties,
                    ])) {
                        continue;
                    }

                    $deliveryService->dispatchForEventTrigger($trigger, $model, $properties);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('EventTrigger: failed to process event', [
                        'trigger_id' => $triggerId,
                        'event_class' => $eventClass,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

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
