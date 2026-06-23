<?php

namespace Ashrafic\FilamentAutomationBridge\Filament\Resources\Pages;

use Ashrafic\FilamentAutomationBridge\Filament\Resources\AutomationTriggerResource;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Ashrafic\FilamentAutomationBridge\Services\TemplateManager;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAutomationTrigger extends EditRecord
{
    protected static string $resource = AutomationTriggerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),

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
                ->form([
                    TextInput::make('template_name')
                        ->label('Template Name')
                        ->required()
                        ->default($this->record->name),
                    Textarea::make('template_description')
                        ->label('Description')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    app(TemplateManager::class)->saveFromTrigger(
                        $this->record,
                        $data['template_name'],
                        $data['template_description'] ?? null,
                    );

                    Notification::make()
                        ->title('Template saved')
                        ->body("Saved as \"{$data['template_name']}\"")
                        ->success()
                        ->send();
                }),
        ];
    }
}
