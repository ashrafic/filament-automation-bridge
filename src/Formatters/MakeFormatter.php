<?php

namespace Ashrafic\FilamentAutomationBridge\Formatters;

use Ashrafic\FilamentAutomationBridge\Contracts\PayloadFormatter;
use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;

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
            'automation_id' => $metadata['automation_id'],
        ];

        return $payload;
    }
}
