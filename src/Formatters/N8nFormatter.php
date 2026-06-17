<?php

namespace Ashrafic\FilamentAutomationBridge\Formatters;

use Ashrafic\FilamentAutomationBridge\Contracts\PayloadFormatter;
use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;

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
