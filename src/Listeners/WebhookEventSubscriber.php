<?php

namespace Ashrafic\FilamentWebhookBridge\Listeners;

use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\DeliveryService;
use Illuminate\Database\Eloquent\Model;

class WebhookEventSubscriber
{
    public function __construct(
        protected DeliveryService $deliveryService,
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
            if (! $this->shouldHandleEloquentEvent($trigger)) {
                continue;
            }

            $this->deliveryService->dispatch($trigger, $model, $eventEnum, $model->getOriginal());
        }
    }

    protected function shouldHandleEloquentEvent(WebhookTrigger $trigger): bool
    {
        return $trigger->isTriggerType('model-event');
    }
}
