<?php

namespace Ashrafic\FilamentAutomationBridge\Concerns;

trait HasAutomationTriggers
{
    public static function getAutomationDisplayName(): string
    {
        return class_basename(static::class);
    }

    public static function getAutomationStatusField(): ?string
    {
        return null;
    }

    public static function getAutomationWatchableFields(): array
    {
        return [];
    }

    public function shouldTriggerAutomations(): bool
    {
        return true;
    }

    public function getAutomationContextData(): array
    {
        return [];
    }
}
