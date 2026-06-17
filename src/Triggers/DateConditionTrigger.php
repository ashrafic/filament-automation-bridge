<?php

namespace Ashrafic\FilamentAutomationBridge\Triggers;

use Ashrafic\FilamentAutomationBridge\Contracts\TriggerContract;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\FieldSchemaAnalyzer;
use Carbon\Carbon;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;

class DateConditionTrigger implements TriggerContract
{
    public static function type(): string
    {
        return 'date-condition';
    }

    public static function name(): string
    {
        return 'Date Condition';
    }

    public static function description(): string
    {
        return 'Fires based on date field logic (X days before/after/on a date)';
    }

    public static function icon(): string
    {
        return 'heroicon-o-calendar-days';
    }

    public static function color(): string
    {
        return 'info';
    }

    public static function configSchema(): array
    {
        return [
            Forms\Components\Select::make('date_field')
                ->label('Date Field')
                ->options(function (Forms\Get $get) {
                    $modelClass = $get('model_class');

                    if (! $modelClass) {
                        return [];
                    }

                    $analyzer = app(FieldSchemaAnalyzer::class);
                    $attributes = $analyzer->getAttributeNames($modelClass);

                    return collect($attributes)
                        ->filter(function ($attr) use ($modelClass) {
                            $name = is_array($attr) ? $attr['name'] : $attr;
                            $type = $analyzer->getAttributeColumnType($modelClass, $name);

                            if ($type === null) {
                                return false;
                            }

                            $dateTypes = ['date', 'datetime', 'timestamp', 'timestamps'];

                            foreach ($dateTypes as $dateType) {
                                if (str_contains(strtolower($type), $dateType)) {
                                    return true;
                                }
                            }

                            return false;
                        })
                        ->mapWithKeys(fn ($attr) => [
                            is_array($attr) ? $attr['name'] : $attr => is_array($attr) ? $attr['name'] : $attr,
                        ])
                        ->toArray();
                })
                ->searchable()
                ->required(),
            Forms\Components\Select::make('condition_type')
                ->label('Condition Type')
                ->options([
                    'before' => 'Days Before',
                    'after' => 'Days After',
                    'on' => 'On Date',
                ])
                ->default('before')
                ->required(),
            Forms\Components\TextInput::make('days')
                ->label('Days')
                ->numeric()
                ->minValue(1)
                ->default(7)
                ->required(),
            Forms\Components\TimePicker::make('time_of_day')
                ->label('Time of Day (optional)')
                ->native(false),
        ];
    }

    public static function defaultConfig(): array
    {
        return ['date_field' => '', 'condition_type' => 'before', 'days' => 7, 'time_of_day' => null];
    }

    public function shouldFire(Model $model, array $config, array $context = []): bool
    {
        $dateField = $config['date_field'] ?? '';

        if (empty($dateField)) {
            return false;
        }

        $dateValue = $model->getAttribute($dateField);

        if ($dateValue === null) {
            return false;
        }

        try {
            $carbonDate = Carbon::parse($dateValue);
        } catch (\Throwable) {
            return false;
        }

        $days = (int) ($config['days'] ?? 7);
        $conditionType = $config['condition_type'] ?? 'before';

        $targetDate = match ($conditionType) {
            'before' => $carbonDate->copy()->subDays($days),
            'after' => $carbonDate->copy()->addDays($days),
            'on' => $carbonDate->copy(),
            default => $carbonDate->copy(),
        };

        $now = now();

        if (! $targetDate->isSameDay($now)) {
            return false;
        }

        $timeOfDay = $config['time_of_day'] ?? null;

        if ($timeOfDay !== null && $timeOfDay !== '') {
            $targetHour = (int) Carbon::parse($timeOfDay)->format('G');

            if ($now->hour !== $targetHour) {
                return false;
            }
        }

        return true;
    }

    public function getContextData(Model $model, array $config): array
    {
        return [
            'date_field' => $config['date_field'] ?? '',
            'date_value' => $model->getAttribute($config['date_field'] ?? ''),
            'condition' => $config['condition_type'] ?? 'before',
            'days' => (int) ($config['days'] ?? 7),
        ];
    }

    public function subscribe(AutomationTrigger $trigger): ?\Closure
    {
        return null;
    }

    public function unsubscribe(AutomationTrigger $trigger): void {}
}
