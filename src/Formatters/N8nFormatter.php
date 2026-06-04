<?php

namespace Ashrafic\FilamentWebhookBridge\Formatters;

use Ashrafic\FilamentWebhookBridge\Contracts\PayloadFormatter;
use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;

class N8nFormatter implements PayloadFormatter
{
    public function destinationType(): DestinationType
    {
        return DestinationType::N8n;
    }

    public function format(array $payload, array $metadata): array
    {
        return ['body' => $payload];
    }
}
