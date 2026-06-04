<?php

namespace Ashrafic\FilamentWebhookBridge\Exceptions;

class InvalidPayloadException extends \RuntimeException
{
    public array $errors = [];

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}