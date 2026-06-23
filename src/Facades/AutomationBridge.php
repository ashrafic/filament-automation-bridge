<?php

namespace Ashrafic\FilamentAutomationBridge\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery dispatch(\Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger $trigger, \Illuminate\Database\Eloquent\Model $model, \Ashrafic\FilamentAutomationBridge\Enums\EventEnum $event, array $original = [])
 * @method static array testConnection(\Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger $trigger)
 * @method static \Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery retry(\Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery $delivery)
 * @method static \Illuminate\Support\Collection getActiveTriggers(string $modelClass, \Ashrafic\FilamentAutomationBridge\Enums\EventEnum $event)
 */
class AutomationBridge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'automation-bridge';
    }
}
