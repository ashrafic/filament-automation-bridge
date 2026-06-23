<?php

namespace Ashrafic\FilamentAutomationBridge\Exceptions;

class TriggerNotFoundException extends \RuntimeException
{
    public function __construct(string $type, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Unknown trigger type: {$type}", $code, $previous);
    }
}
