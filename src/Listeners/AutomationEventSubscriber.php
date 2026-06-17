<?php

namespace Ashrafic\FilamentAutomationBridge\Listeners;

use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Ashrafic\FilamentAutomationBridge\Triggers\TriggerManager;
use Illuminate\Database\Eloquent\Model;

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
        $eventType = str_replace('eloquent.', '', $parts[0]);
        $modelClass = $parts[1] ?? null;

        if ($modelClass === null) {
            return;
        }

        $eventEnum = EventEnum::tryFrom($eventType);

        if ($eventEnum === null) {
            return;
        }

        $modelClass = str_replace('/', '\\', $modelClass);

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
                $context = $triggerContract->getContextData($model, $trigger->trigger_config ?? []);
            } elseif ($trigger->isTriggerType('status-changed')) {
                $triggerContract = $this->triggerManager->get($trigger->trigger_type);

                if (! $triggerContract->shouldFire($model, $trigger->trigger_config ?? [], ['event' => $eventEnum->value])) {
                    continue;
                }

                $context = $triggerContract->getContextData($model, $trigger->trigger_config ?? []);
            }

            $this->deliveryService->dispatch($trigger, $model, $eventEnum, $model->getOriginal(), $context);
        }
    }
}
