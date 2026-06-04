<?php

namespace Ashrafic\FilamentWebhookBridge\Contracts;

use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Illuminate\Database\Eloquent\Model;

interface TriggerContract
{
    /** Unique identifier (kebab-case). e.g. 'model-created', 'schedule', 'manual' */
    public static function type(): string;

    /** Human-readable name for the UI */
    public static function name(): string;

    /** Description shown in trigger type selection */
    public static function description(): string;

    /** Heroicon name */
    public static function icon(): string;

    /** Filament color: primary, success, warning, danger, info */
    public static function color(): string;

    /** Filament form schema for trigger-specific configuration. Return [] if none. */
    public static function configSchema(): array;

    /** Default configuration values */
    public static function defaultConfig(): array;

    /** Determine if this trigger should fire the webhook */
    public function shouldFire(Model $model, array $config, array $context = []): bool;

    /** Extra context data to make available in the payload for this trigger */
    public function getContextData(Model $model, array $config): array;

    /** Register event listeners for this trigger type (called when trigger is activated). Return the cleanup callable. */
    public function subscribe(WebhookTrigger $trigger): ?\Closure;

    /** Called when trigger is deactivated or deleted to clean up listeners */
    public function unsubscribe(WebhookTrigger $trigger): void;
}
