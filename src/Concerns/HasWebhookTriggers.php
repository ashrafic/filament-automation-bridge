<?php

namespace Ashrafic\FilamentWebhookBridge\Concerns;

trait HasWebhookTriggers
{
    public static function getWebhookDisplayName(): string
    {
        return class_basename(static::class);
    }

    public static function getWebhookStatusField(): ?string
    {
        return null;
    }

    public static function getWebhookWatchableFields(): array
    {
        return [];
    }

    public function shouldTriggerWebhooks(): bool
    {
        return true;
    }

    public function getWebhookContextData(): array
    {
        return [];
    }
}
