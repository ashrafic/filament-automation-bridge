<?php

namespace Ashrafic\FilamentWebhookBridge\Triggers;

use Ashrafic\FilamentWebhookBridge\Contracts\TriggerContract;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\Model;

class ManualTrigger implements TriggerContract
{
    public static function type(): string
    {
        return 'manual';
    }

    public static function name(): string
    {
        return 'Manual';
    }

    public static function description(): string
    {
        return 'User-initiated webhook via a button in your Filament resource';
    }

    public static function icon(): string
    {
        return 'heroicon-o-hand-raised';
    }

    public static function color(): string
    {
        return 'success';
    }

    public static function configSchema(): array
    {
        return [
            Placeholder::make('manual_trigger_info')
                ->content('This trigger is fired manually from a Filament resource button. No additional configuration is required.'),
        ];
    }

    public static function defaultConfig(): array
    {
        return [];
    }

    public function shouldFire(Model $model, array $config, array $context = []): bool
    {
        return true;
    }

    public function getContextData(Model $model, array $config): array
    {
        return [];
    }

    public function subscribe(WebhookTrigger $trigger): ?\Closure
    {
        return null;
    }

    public function unsubscribe(WebhookTrigger $trigger): void {}
}
