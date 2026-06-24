<?php

namespace Ashrafic\FilamentAutomationBridge\Filament\Components;

use Ashrafic\FilamentAutomationBridge\Conditions\ConditionRegistry;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Exceptions\ConditionEvaluationException;
use Ashrafic\FilamentAutomationBridge\Services\FieldSchemaAnalyzer;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class ConditionBuilder extends Repeater
{
    protected string|Closure|null $modelClass = null;

    protected string|Closure|null $event = null;

    public function modelClass(string|Closure|null $modelClass): static
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    public function getModelClass(): ?string
    {
        return $this->evaluate($this->modelClass);
    }

    public function event(string|Closure|null $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getEvent(): ?string
    {
        return $this->evaluate($this->event);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-automation-bridge::automation-bridge.form.conditions'))
            ->maxItems(10)
            ->collapsible()
            ->collapsed(fn ($state) => empty($state))
            ->columnSpanFull()
            ->columns(2)
            ->schema([
                Select::make('field')
                    ->label(__('filament-automation-bridge::automation-bridge.form.condition_field'))
                    ->required()
                    ->options(function (Get $get, ConditionBuilder $component) {
                        $modelClass = $component->getModelClass();

                        if (! $modelClass || ! class_exists($modelClass)) {
                            return [];
                        }

                        $analyzer = app(FieldSchemaAnalyzer::class);
                        $attributes = $analyzer->getAttributeNames($modelClass);

                        return collect($attributes)
                            ->mapWithKeys(fn ($attr) => [
                                (is_array($attr) ? $attr['name'] : $attr) => (is_array($attr) ? $attr['name'] : $attr),
                            ])
                            ->toArray();
                    }),
                Select::make('operator')
                    ->label(__('filament-automation-bridge::automation-bridge.form.condition_operator'))
                    ->required()
                    ->options(function (ConditionBuilder $component) {
                        $registry = app(ConditionRegistry::class);
                        $event = $component->getEvent();

                        if ($event && EventEnum::tryFrom($event) === EventEnum::Created) {
                            $operators = $registry->forCreatedEvent();
                        } else {
                            $operators = $registry->all();
                        }

                        return collect($operators)
                            ->mapWithKeys(fn ($op) => [$op->key() => $op->label()])
                            ->toArray();
                    })
                    ->live()
                    ->afterStateUpdated(function ($state, Select $component) {
                        if (blank($state)) {
                            return;
                        }

                        $registry = app(ConditionRegistry::class);

                        try {
                            $operator = $registry->get($state);

                            if (! $operator->requiresValue()) {
                                $component->getContainer()->getComponent('value')->state(null);
                            }
                        } catch (ConditionEvaluationException) {
                        }
                    }),
                TextInput::make('value')
                    ->label(__('filament-automation-bridge::automation-bridge.form.condition_value'))
                    ->visible(function (Get $get) {
                        $operatorKey = $get('operator');

                        if (blank($operatorKey)) {
                            return true;
                        }

                        $registry = app(ConditionRegistry::class);

                        try {
                            return $registry->get($operatorKey)->requiresValue();
                        } catch (ConditionEvaluationException) {
                            return true;
                        }
                    }),
                Select::make('logic')
                    ->label(__('filament-automation-bridge::automation-bridge.form.condition_logic'))
                    ->options([
                        'and' => __('filament-automation-bridge::automation-bridge.enums.condition_logic.and'),
                        'or' => __('filament-automation-bridge::automation-bridge.enums.condition_logic.or'),
                    ])
                    ->default('and')
                    ->visible(fn (string $context) => $context === 'edit'),
            ]);
    }
}
