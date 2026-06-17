<?php

namespace Ashrafic\FilamentAutomationBridge\Triggers;

use Ashrafic\FilamentAutomationBridge\Contracts\TriggerContract;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\ConditionEvaluator;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;

class EventTrigger implements TriggerContract
{
    public static function type(): string
    {
        return 'event';
    }

    public static function name(): string
    {
        return 'Event';
    }

    public static function description(): string
    {
        return 'Fires when a Laravel event is dispatched';
    }

    public static function icon(): string
    {
        return 'heroicon-o-bell-alert';
    }

    public static function color(): string
    {
        return 'danger';
    }

    public static function configSchema(): array
    {
        return [
            TextInput::make('trigger_config.event_class')
                ->label('Event Class')
                ->placeholder('App\Events\OrderShipped')
                ->helperText('Enter the fully-qualified class name of the event to listen for')
                ->required(),
        ];
    }

    public static function defaultConfig(): array
    {
        return [
            'event_class' => '',
            'conditions' => null,
        ];
    }

    public function shouldFire(Model $model, array $config, array $context = []): bool
    {
        $eventClass = $config['event_class'] ?? '';

        if (empty($eventClass)) {
            return false;
        }

        $dispatchedClass = $context['event_class'] ?? '';

        if ($dispatchedClass !== $eventClass) {
            return false;
        }

        if (! empty($config['conditions'])) {
            $eventProperties = $context['event_properties'] ?? [];

            $evaluator = app(ConditionEvaluator::class);

            return $evaluator->evaluate($model, $config['conditions'], $eventProperties);
        }

        return true;
    }

    public function getContextData(Model $model, array $config): array
    {
        return [
            'event_class' => $config['event_class'] ?? '',
        ];
    }

    public function subscribe(AutomationTrigger $trigger): ?\Closure
    {
        $config = $trigger->trigger_config ?? [];
        $eventClass = $config['event_class'] ?? '';

        if (empty($eventClass) || ! class_exists($eventClass)) {
            return null;
        }

        TriggerManager::addSubscribedEvent($eventClass, $trigger->id);

        return function () use ($eventClass, $trigger) {
            TriggerManager::removeSubscribedEvent($eventClass, $trigger->id);
        };
    }

    public function unsubscribe(AutomationTrigger $trigger): void
    {
        $config = $trigger->trigger_config ?? [];
        $eventClass = $config['event_class'] ?? '';

        if (! empty($eventClass)) {
            TriggerManager::removeSubscribedEvent($eventClass, $trigger->id);
        }
    }
}
