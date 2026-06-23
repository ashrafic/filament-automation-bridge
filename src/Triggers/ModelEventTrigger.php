<?php

namespace Ashrafic\FilamentAutomationBridge\Triggers;

use Ashrafic\FilamentAutomationBridge\Contracts\TriggerContract;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\FieldSchemaAnalyzer;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

class ModelEventTrigger implements TriggerContract
{
    public static function type(): string
    {
        return 'model-event';
    }

    public static function name(): string
    {
        return 'Model Event';
    }

    public static function description(): string
    {
        return 'Fires when a model record is created, updated, deleted, restored, or force deleted';
    }

    public static function icon(): string
    {
        return 'heroicon-o-cube';
    }

    public static function color(): string
    {
        return 'primary';
    }

    public static function configSchema(): array
    {
        return [
            Forms\Components\Select::make('event')
                ->options(EventEnum::class)
                ->required()
                ->live(),
            Forms\Components\Select::make('watch_fields')
                ->multiple()
                ->options(function (Get $get) {
                    $modelClass = $get('model_class');

                    if (! $modelClass) {
                        return [];
                    }

                    $analyzer = app(FieldSchemaAnalyzer::class);
                    $attributes = $analyzer->getAttributeNames($modelClass);

                    return collect($attributes)
                        ->mapWithKeys(fn ($attr) => [
                            is_array($attr) ? $attr['name'] : $attr => is_array($attr) ? $attr['name'] : $attr,
                        ])
                        ->toArray();
                })
                ->visible(fn (Get $get) => $get('event') === EventEnum::Updated->value),
        ];
    }

    public static function defaultConfig(): array
    {
        return ['event' => 'created', 'watch_fields' => []];
    }

    public function shouldFire(Model $model, array $config, array $context = []): bool
    {
        if (! empty($config['watch_fields']) && ($config['event'] ?? '') === 'updated') {
            if (($context['event'] ?? '') !== 'updated') {
                return false;
            }

            $dirty = $model->getDirty();
            $watchedChanged = false;

            foreach ($config['watch_fields'] as $field) {
                if (array_key_exists($field, $dirty)) {
                    $watchedChanged = true;

                    break;
                }
            }

            if (! $watchedChanged) {
                return false;
            }
        }

        return true;
    }

    public function getContextData(Model $model, array $config): array
    {
        return ['trigger_event' => $config['event'] ?? 'created'];
    }

    public function subscribe(AutomationTrigger $trigger): ?\Closure
    {
        return null;
    }

    public function unsubscribe(AutomationTrigger $trigger): void {}
}
