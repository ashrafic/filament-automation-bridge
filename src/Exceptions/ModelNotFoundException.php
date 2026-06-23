<?php

namespace Ashrafic\FilamentAutomationBridge\Exceptions;

class ModelNotFoundException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function forClass(string $class): self
    {
        return new self("Model class does not exist: {$class}");
    }

    public static function forTrigger(int $triggerId): self
    {
        return new self("Automation trigger with ID {$triggerId} not found.");
    }

    public static function forDelivery(int $deliveryId): self
    {
        return new self("Automation delivery with ID {$deliveryId} not found.");
    }
}
