<?php

namespace Ashrafic\FilamentWebhookBridge\Exceptions;

class ModelNotFoundException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}