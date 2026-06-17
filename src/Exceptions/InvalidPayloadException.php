<?php

namespace Ashrafic\FilamentAutomationBridge\Exceptions;

class InvalidPayloadException extends \RuntimeException
{
    public array $errors = [];

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function classDoesNotExist(string $class): self
    {
        return new self("Model class does not exist: {$class}");
    }

    public static function emptyTemplate(): self
    {
        return new self('Custom payload template is empty.');
    }

    public static function templateErrors(array $errors): self
    {
        $exception = new self('Invalid payload template: '.implode('; ', $errors));
        $exception->errors = $errors;

        return $exception;
    }

    public static function invalidJson(string $error): self
    {
        return new self("Invalid JSON in payload template: {$error}");
    }

    public static function payloadTooLarge(int $size, int $maxSize): self
    {
        $sizeMb = round($size / 1024 / 1024, 2);
        $maxMb = round($maxSize / 1024 / 1024, 2);

        return new self("Payload size ({$sizeMb}MB) exceeds maximum allowed size ({$maxMb}MB).");
    }
}
