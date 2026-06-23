<?php

namespace Ashrafic\FilamentAutomationBridge\Filament\Components;

use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;

class TestConnectionAction
{
    public static function make(): Action
    {
        return Action::make('test_connection')
            ->label('Test Connection')
            ->icon('heroicon-o-signal')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Test Connection')
            ->modalDescription('This will send a test request to the configured destination URL using sample data. No record will be saved.')
            ->action(function (Get $get) {
                $modelClass = $get('model_class');
                $destinationUrl = $get('destination_url');

                if (blank($modelClass) || blank($destinationUrl)) {
                    Notification::make()
                        ->title('Validation Error')
                        ->body('Please fill in the Model and Destination URL before testing.')
                        ->danger()
                        ->send();

                    return;
                }

                $trigger = new AutomationTrigger;
                $trigger->model_class = $modelClass;
                $trigger->event = EventEnum::tryFrom($get('event') ?? 'created') ?? EventEnum::Created;
                $trigger->destination_type = DestinationType::tryFrom($get('destination_type') ?? 'custom') ?? DestinationType::Custom;
                $trigger->destination_url = $destinationUrl;
                $trigger->payload_mode = PayloadMode::tryFrom($get('payload_mode') ?? 'summary') ?? PayloadMode::Summary;
                $trigger->field_mapping = $get('field_mapping') ?? [];
                $trigger->custom_payload_template = $get('custom_payload_template') ?? '';
                $trigger->secret = $get('secret') ?? '';
                $trigger->request_timeout = $get('request_timeout') ?? 30;
                $trigger->max_retries = 0;
                $trigger->id = 0;

                try {
                    $deliveryService = app(DeliveryService::class);
                    $result = $deliveryService->testConnection($trigger);

                    if ($result['success']) {
                        $duration = $result['duration_ms'] ? " ({$result['duration_ms']}ms)" : '';

                        Notification::make()
                            ->title('Connection Successful')
                            ->body("HTTP {$result['http_status']}{$duration}")
                            ->success()
                            ->send();
                    } else {
                        $status = $result['http_status'] ? "HTTP {$result['http_status']}" : 'Connection failed';
                        $error = $result['error'] ? ": {$result['error']}" : '';

                        Notification::make()
                            ->title('Connection Failed')
                            ->body("{$status}{$error}")
                            ->danger()
                            ->duration(10000)
                            ->send();
                    }
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Test Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->duration(10000)
                        ->send();
                }
            });
    }
}
