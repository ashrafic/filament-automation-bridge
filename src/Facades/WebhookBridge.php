<?php

namespace Ashrafic\FilamentWebhookBridge\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery dispatch(\Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger $trigger, \Illuminate\Database\Eloquent\Model $model, \Ashrafic\FilamentWebhookBridge\Enums\EventEnum $event, array $original = [])
 * @method static array testConnection(\Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger $trigger)
 * @method static \Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery retry(\Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery $delivery)
 * @method static \Illuminate\Support\Collection getActiveTriggers(string $modelClass, \Ashrafic\FilamentWebhookBridge\Enums\EventEnum $event)
 */
class WebhookBridge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'webhook-bridge';
    }
}