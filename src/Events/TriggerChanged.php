<?php

namespace Ashrafic\FilamentWebhookBridge\Events;

use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TriggerChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WebhookTrigger $trigger,
        public string $action,
    ) {}
}