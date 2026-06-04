<?php

namespace Ashrafic\FilamentWebhookBridge\Filament\Components;

use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Enums\PayloadMode;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\PayloadBuilder;
use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\View;
use Filament\Forms\Get;

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
            View::make('filament-webhook-bridge::components.payload-preview')
                ->viewData(function (Get $get, PayloadPreview $component) {
                    $modelClass = $component->getModelClass();
                    $eventType = $component->getEvent();
                    $destinationType = $component->getDestinationType();

                    if (! $modelClass || ! class_exists($modelClass)) {
                        return ['json' => '// Select a model to see a payload preview', 'destination' => null];
                    }

                    $trigger = new WebhookTrigger;
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
                        $payload = ['error' => 'Unable to generate preview: '.$e->getMessage()];
                    }

                    return [
                        'json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                        'destination' => $trigger->destination_type?->getLabel(),
                    ];
                }),
        ]);
    }
}
