<?php

namespace Ashrafic\FilamentAutomationBridge;

use Ashrafic\FilamentAutomationBridge\Commands\InstallCommand;
use Ashrafic\FilamentAutomationBridge\Commands\ModelCacheCommand;
use Ashrafic\FilamentAutomationBridge\Commands\ProcessScheduledTriggersCommand;
use Ashrafic\FilamentAutomationBridge\Commands\PruneDeliveryLogsCommand;
use Ashrafic\FilamentAutomationBridge\Commands\SyncHistoricalRecordsCommand;
use Ashrafic\FilamentAutomationBridge\Commands\TestConnectionCommand;
use Ashrafic\FilamentAutomationBridge\Conditions\ConditionRegistry;
use Ashrafic\FilamentAutomationBridge\Formatters\CustomFormatter;
use Ashrafic\FilamentAutomationBridge\Formatters\MakeFormatter;
use Ashrafic\FilamentAutomationBridge\Formatters\N8nFormatter;
use Ashrafic\FilamentAutomationBridge\Formatters\ZapierFormatter;
use Ashrafic\FilamentAutomationBridge\Listeners\AutomationEventSubscriber;
use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Ashrafic\FilamentAutomationBridge\Triggers\DateConditionTrigger;
use Ashrafic\FilamentAutomationBridge\Triggers\EventTrigger;
use Ashrafic\FilamentAutomationBridge\Triggers\ManualTrigger;
use Ashrafic\FilamentAutomationBridge\Triggers\ModelEventTrigger;
use Ashrafic\FilamentAutomationBridge\Triggers\ScheduleTrigger;
use Ashrafic\FilamentAutomationBridge\Triggers\StatusChangedTrigger;
use Ashrafic\FilamentAutomationBridge\Triggers\TriggerManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;

class FilamentAutomationBridgeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-automation-bridge')
            ->hasConfigFile()
            ->hasMigrations([
                'create_automation_triggers_table',
                'create_automation_deliveries_table',
                'create_automation_templates_table',
                'add_trigger_type_to_automation_triggers_table',
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
            $manager->register(DateConditionTrigger::class);
            $manager->register(ManualTrigger::class);
            $manager->register(EventTrigger::class);

            return $manager;
        });

        $this->app->singleton('automation-bridge', DeliveryService::class);

        $this->app->tag([
            ZapierFormatter::class,
            MakeFormatter::class,
            N8nFormatter::class,
            CustomFormatter::class,
        ], 'automation-bridge.formatters');
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        if (config('filament-automation-bridge.models.paths')) {
            Event::listen('eloquent.*', [AutomationEventSubscriber::class, 'handle']);
        }

        $this->app->make(Schedule::class)
            ->command('automation-bridge:process-scheduled')
            ->everyMinute();

        if (config('filament-automation-bridge.retention.prune_enabled', true)) {
            $this->app->make(Schedule::class)
                ->command('automation-bridge:prune-logs')
                ->daily();
        }

        Event::listen('*', function (string $eventName, array $payload) {
            if (! TriggerManager::hasEventSubscriptions()) {
                return;
            }

            $eventClass = $eventName;

            if (is_object($eventName)) {
                $eventClass = get_class($eventName);
            }

            $triggerIds = TriggerManager::getTriggerIdsForEvent($eventClass);

            if (empty($triggerIds)) {
                return;
            }

            $eventObject = $payload[0] ?? null;

            if ($eventObject === null) {
                return;
            }

            $triggerManager = app(TriggerManager::class);
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
                if ($value instanceof Model) {
                    $model = $value;
                    break;
                }
            }

            foreach ($triggerIds as $triggerId) {
                try {
                    $trigger = AutomationTrigger::find($triggerId);

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
                    Log::error('EventTrigger: failed to process event', [
                        'trigger_id' => $triggerId,
                        'event_class' => $eventClass,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        if (class_exists(WebhookCallSucceededEvent::class)) {
            Event::listen(WebhookCallSucceededEvent::class, function (WebhookCallSucceededEvent $event) {
                $delivery = AutomationDelivery::where('uuid', $event->uuid ?? '')->first();

                if ($delivery) {
                    app(DeliveryService::class)->handleSpatieSuccess($delivery, $event->response);
                }
            });

            Event::listen(WebhookCallFailedEvent::class, function (WebhookCallFailedEvent $event) {
                $delivery = AutomationDelivery::where('uuid', $event->uuid ?? '')->first();

                if ($delivery) {
                    app(DeliveryService::class)->handleSpatieFailure($delivery, new \RuntimeException($event->errorMessage ?? 'Automation call failed'));
                }
            });
        }
    }
}
