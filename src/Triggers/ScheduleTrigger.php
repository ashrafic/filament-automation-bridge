<?php

namespace Ashrafic\FilamentAutomationBridge\Triggers;

use Ashrafic\FilamentAutomationBridge\Contracts\TriggerContract;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\ModelDiscoveryService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

class ScheduleTrigger implements TriggerContract
{
    public static function type(): string
    {
        return 'schedule';
    }

    public static function name(): string
    {
        return 'Schedule';
    }

    public static function description(): string
    {
        return 'Fires on a cron schedule (hourly, daily, weekly, monthly, custom cron expression)';
    }

    public static function icon(): string
    {
        return 'heroicon-o-clock';
    }

    public static function color(): string
    {
        return 'info';
    }

    public static function configSchema(): array
    {
        return [
            Select::make('model_class')
                ->label('Model')
                ->options(fn () => app(ModelDiscoveryService::class)->getAllModels())
                ->searchable()
                ->required(),
            Select::make('trigger_config.schedule_type')
                ->label('Schedule Type')
                ->options([
                    'hourly' => 'Every Hour',
                    'daily' => 'Daily',
                    'weekly' => 'Weekly',
                    'monthly' => 'Monthly',
                    'custom' => 'Custom Cron',
                ])
                ->default('daily')
                ->live()
                ->required(),
            TextInput::make('trigger_config.custom_cron')
                ->label('Custom Cron Expression')
                ->placeholder('* * * * *')
                ->visible(fn (Get $get) => $get('trigger_config.schedule_type') === 'custom'),
        ];
    }

    public static function defaultConfig(): array
    {
        return [
            'schedule_type' => 'daily',
            'custom_cron' => null,
        ];
    }

    public function shouldFire(Model $model, array $config, array $context = []): bool
    {
        return true;
    }

    public function getContextData(Model $model, array $config): array
    {
        return [
            'schedule_type' => $config['schedule_type'] ?? 'daily',
            'triggered_at' => now()->toIso8601String(),
        ];
    }

    public function subscribe(AutomationTrigger $trigger): ?\Closure
    {
        return null;
    }

    public function unsubscribe(AutomationTrigger $trigger): void {}
}
