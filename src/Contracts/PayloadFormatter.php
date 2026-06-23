<?php

namespace Ashrafic\FilamentAutomationBridge\Contracts;

use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;

interface PayloadFormatter
{
    public function format(array $payload, array $metadata): array;

    public function destinationType(): DestinationType;
}
