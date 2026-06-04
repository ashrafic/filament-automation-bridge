<?php

namespace Ashrafic\FilamentWebhookBridge\Filament\Resources\Pages;

use Ashrafic\FilamentWebhookBridge\Filament\Resources\WebhookTriggerResource;
use Ashrafic\FilamentWebhookBridge\Services\DeliveryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWebhookTrigger extends EditRecord
{
    protected static string $resource = WebhookTriggerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_connection')
                ->label('Test Connection')
                ->icon('heroicon-o-signal')
                ->action(function () {
                    $result = app(DeliveryService::class)->testConnection($this->record);

                    if ($result['success']) {
                        Notification::make()
                            ->title('Connection successful')
                            ->body("HTTP {$result['http_status']} — {$result['duration_ms']}ms")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Connection failed')
                            ->body($result['error'] ?? "HTTP {$result['http_status']}")
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('save_as_template')
                ->label('Save as Template')
                ->icon('heroicon-o-bookmark')
                ->action(function () {
                    Notification::make()
                        ->title('Template saved')
                        ->body('The trigger configuration has been saved as a template. (Placeholder — full implementation coming soon)')
                        ->success()
                        ->send();
                }),
        ];
    }
}
