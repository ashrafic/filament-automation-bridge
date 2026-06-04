<?php

namespace Ashrafic\FilamentWebhookBridge\Filament;

use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\DeliveryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class ManualTriggerAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->icon('heroicon-o-paper-airplane')
            ->label('Send Webhook')
            ->color('success')
            ->form(fn () => [
                Select::make('trigger_id')
                    ->label('Select Webhook Trigger')
                    ->options(function () {
                        $record = $this->getRecord();
                        if (! $record) {
                            return [];
                        }

                        $modelClass = get_class($record);

                        return WebhookTrigger::active()
                            ->where('trigger_type', 'manual')
                            ->where('model_class', $modelClass)
                            ->pluck('name', 'id');
                    })
                    ->required(),
            ])
            ->action(function (array $data): void {
                $trigger = WebhookTrigger::find($data['trigger_id']);
                $record = $this->getRecord();

                if (! $trigger || ! $record) {
                    return;
                }

                $deliveryService = app(DeliveryService::class);
                $delivery = $deliveryService->dispatchForManualTrigger($trigger, $record);

                Notification::make()
                    ->title($delivery ? 'Webhook sent successfully' : 'Webhook queued for delivery')
                    ->success()
                    ->send();
            })
            ->visible(function () {
                $record = $this->getRecord();
                if (! $record) {
                    return false;
                }

                return WebhookTrigger::active()
                    ->where('trigger_type', 'manual')
                    ->where('model_class', get_class($record))
                    ->exists();
            });
    }
}
