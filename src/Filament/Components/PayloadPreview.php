<?php

namespace Ashrafic\FilamentAutomationBridge\Filament\Components;

use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\PayloadBuilder;
use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Utilities\Get;

class PayloadPreview extends Component
{
    protected string|Closure|null $modelClass = null;

    protected string|Closure|null $event = null;

    protected string|Closure|null $destinationType = null;

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

    public function destinationType(string|Closure|null $destinationType): static
    {
        $this->destinationType = $destinationType;

        return $this;
    }

    public function getDestinationType(): ?string
    {
        return $this->evaluate($this->destinationType);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema([
            View::make('filament-automation-bridge::components.payload-preview')
                ->viewData(function (Get $get, PayloadPreview $component) {
                    $modelClass = $component->getModelClass();
                    $eventType = $component->getEvent();
                    $destinationType = $component->getDestinationType();

                    if (! $modelClass || ! class_exists($modelClass)) {
                        return ['json' => __('filament-automation-bridge::form.payload_preview_fallback'), 'destination' => null];
                    }

                    $trigger = new AutomationTrigger;
                    $trigger->model_class = $modelClass;
                    $trigger->event = $eventType ? EventEnum::tryFrom($eventType) ?? EventEnum::Created : EventEnum::Created;
                    $trigger->destination_type = $destinationType ? DestinationType::tryFrom($destinationType) ?? DestinationType::Custom : DestinationType::Custom;
                    $trigger->payload_mode = PayloadMode::tryFrom($get('payload_mode') ?? 'summary') ?? PayloadMode::Summary;
                    $trigger->field_mapping = $get('field_mapping') ?? [];
                    $trigger->custom_payload_template = $get('custom_payload_template') ?? '';
                    $trigger->id = 0;

                    try {
                        $payloadBuilder = app(PayloadBuilder::class);
                        $payload = $payloadBuilder->buildSample($trigger);

                        if ($trigger->destination_type) {
                            $payload = $payloadBuilder->formatPayload($payload, $trigger->destination_type);
                        }
                    } catch (\Throwable $e) {
                        $payload = ['error' => __('filament-automation-bridge::form.payload_preview_error').$e->getMessage()];
                    }

                    return [
                        'json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                        'destination' => $trigger->destination_type?->getLabel(),
                    ];
                }),
        ]);
    }
}
