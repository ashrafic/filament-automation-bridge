<?php

namespace Ashrafic\FilamentWebhookBridge\Contracts;

use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;

interface PayloadFormatter
{
    public function format(array $payload, array $metadata): array;

    public function destinationType(): DestinationType;
}
