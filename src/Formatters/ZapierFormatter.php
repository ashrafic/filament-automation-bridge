<?php

namespace Ashrafic\FilamentAutomationBridge\Formatters;

use Ashrafic\FilamentAutomationBridge\Contracts\PayloadFormatter;
use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;

class ZapierFormatter implements PayloadFormatter
{
    public function destinationType(): DestinationType
    {
        return DestinationType::Zapier;
    }

    public function format(array $payload, array $metadata): array
    {
        $flat = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $flat[$subKey] = $subValue;
                }
            } else {
                $flat[$key] = $value;
            }
        }

        $flat['event'] = $metadata['event'];
        $flat['triggered_at'] = $metadata['triggered_at'];
        $flat['automation_id'] = $metadata['automation_id'];

        return $flat;
    }
}
