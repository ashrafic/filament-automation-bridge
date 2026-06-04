<?php

namespace Ashrafic\FilamentWebhookBridge\Triggers;

use Ashrafic\FilamentWebhookBridge\Contracts\TriggerContract;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\FieldSchemaAnalyzer;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;

class StatusChangedTrigger implements TriggerContract
{
    public static function type(): string
    {
        return 'status-changed';
    }

    public static function name(): string
    {
        return 'Status Changed';
    }

    public static function description(): string
    {
        return 'Fires when a model status field transitions between specific values';
    }

    public static function icon(): string
    {
        return 'heroicon-o-arrows-right-left';
    }

    public static function color(): string
    {
        return 'warning';
    }

    public static function configSchema(): array
    {
        return [
            Forms\Components\Select::make('status_field')
                ->options(function (Forms\Get $get) {
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
                ->default('status')
                ->required(),
            Forms\Components\TextInput::make('from_status')
                ->label('From Status')
                ->placeholder('Any previous status'),
            Forms\Components\TextInput::make('to_status')
                ->label('To Status')
                ->placeholder('New status value')
                ->required(),
        ];
    }

    public static function defaultConfig(): array
    {
        return ['status_field' => 'status', 'from_status' => null, 'to_status' => ''];
    }

    public function shouldFire(Model $model, array $config, array $context = []): bool
    {
        if (($context['event'] ?? '') !== 'updated') {
            return false;
        }

        $statusField = $config['status_field'] ?? 'status';

        if (! $model->wasChanged($statusField)) {
            return false;
        }

        $fromStatus = $config['from_status'] ?? null;
        $toStatus = $config['to_status'] ?? '';

        if ($fromStatus !== null && $fromStatus !== '') {
            $original = $model->getOriginal($statusField);

            if ((string) $original !== (string) $fromStatus) {
                return false;
            }
        }

        $newValue = $model->getAttribute($statusField);

        if ((string) $newValue !== (string) $toStatus) {
            return false;
        }

        return true;
    }

    public function getContextData(Model $model, array $config): array
    {
        $statusField = $config['status_field'] ?? 'status';

        return [
            'previous_status' => $model->getOriginal($statusField),
            'new_status' => $model->getAttribute($statusField),
        ];
    }

    public function subscribe(WebhookTrigger $trigger): ?\Closure
    {
        return null;
    }

    public function unsubscribe(WebhookTrigger $trigger): void {}
}
