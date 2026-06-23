<?php

namespace Ashrafic\FilamentAutomationBridge\Events;

use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AutomationDeliveryCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public AutomationDelivery $delivery,
    ) {}
}
