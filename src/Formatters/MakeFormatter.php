<?php

namespace Ashrafic\FilamentWebhookBridge\Formatters;

use Ashrafic\FilamentWebhookBridge\Contracts\PayloadFormatter;
use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;

class MakeFormatter implements PayloadFormatter
{
    public function destinationType(): DestinationType
    {
        return DestinationType::Make;
    }

    public function format(array $payload, array $metadata): array
    {
        $payload['__metadata'] = [
            'event' => $metadata['event'],
            'triggered_at' => $metadata['triggered_at'],
            'webhook_id' => $metadata['webhook_id'],
        ];

        return $payload;
    }
}