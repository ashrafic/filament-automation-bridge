<?php

namespace Ashrafic\FilamentWebhookBridge\Events;

use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookDeliveryCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WebhookDelivery $delivery,
    ) {}
}
