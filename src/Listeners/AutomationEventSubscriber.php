<?php

namespace Ashrafic\FilamentAutomationBridge\Listeners;

use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Ashrafic\FilamentAutomationBridge\Triggers\TriggerManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AutomationEventSubscriber
{
    public function __construct(
        protected DeliveryService $deliveryService,
        protected TriggerManager $triggerManager,
    ) {}

    public function handle(string $event, array $payload): void
    {
        if (empty($payload) || ! ($payload[0] instanceof Model)) {
            return;
        }

        $model = $payload[0];

        $parts = explode(':', $event, 2);
        $eventType = Str::snake(str_replace('eloquent.', '', $parts[0]));
        $modelClass = $parts[1] ?? null;

        if ($modelClass === null) {
            return;
        }

        $eventEnum = EventEnum::tryFrom($eventType);

        if ($eventEnum === null) {
            return;
        }

        $modelClass = trim(str_replace('/', '\\', $modelClass));

        $triggers = $this->deliveryService->getActiveTriggers($modelClass, $eventEnum);

        if ($triggers->isEmpty()) {
            return;
        }

        foreach ($triggers as $trigger) {
            $context = [];

            if ($trigger->isTriggerType('model-event')) {
                $configEvent = $trigger->trigger_config['event'] ?? null;

                if ($configEvent !== null && $configEvent !== $eventEnum->value) {
                    continue;
                }

                $triggerContract = $this->triggerManager->get($trigger->trigger_type);
                $config = array_merge($trigger->trigger_config ?? [], ['event' => $eventEnum->value]);
                $context = $triggerContract->getContextData($model, $config);
            } elseif ($trigger->isTriggerType('status-changed')) {
                $triggerContract = $this->triggerManager->get($trigger->trigger_type);

                if (! $triggerContract->shouldFire($model, $trigger->trigger_config ?? [], ['event' => $eventEnum->value])) {
                    continue;
                }

                $config = array_merge($trigger->trigger_config ?? [], ['event' => $eventEnum->value]);
                $context = $triggerContract->getContextData($model, $config);
            }

            $this->deliveryService->dispatch($trigger, $model, $eventEnum, $model->getOriginal(), $context);
        }
    }
}
