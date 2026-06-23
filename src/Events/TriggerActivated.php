<?php

namespace Ashrafic\FilamentAutomationBridge\Events;

use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TriggerActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public AutomationTrigger $trigger,
    ) {}
}
