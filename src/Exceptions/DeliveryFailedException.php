<?php

namespace Ashrafic\FilamentWebhookBridge\Exceptions;

class DeliveryFailedException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}