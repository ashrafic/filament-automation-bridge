<?php

namespace Ashrafic\FilamentAutomationBridge\Filament;

use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
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
            ->label('Send Automation')
            ->color('success')
            ->form(fn () => [
                Select::make('trigger_id')
                    ->label('Select Automation Trigger')
                    ->options(function () {
                        $record = $this->getRecord();
                        if (! $record) {
                            return [];
                        }

                        $modelClass = get_class($record);

                        return AutomationTrigger::active()
                            ->where('trigger_type', 'manual')
                            ->where('model_class', $modelClass)
                            ->pluck('name', 'id');
                    })
                    ->required(),
            ])
            ->action(function (array $data): void {
                $trigger = AutomationTrigger::find($data['trigger_id']);
                $record = $this->getRecord();

                if (! $trigger || ! $record) {
                    return;
                }

                $deliveryService = app(DeliveryService::class);
                $delivery = $deliveryService->dispatchForManualTrigger($trigger, $record);

                Notification::make()
                    ->title($delivery ? 'Automation sent successfully' : 'Automation queued for delivery')
                    ->success()
                    ->send();
            })
            ->visible(function () {
                $record = $this->getRecord();
                if (! $record) {
                    return false;
                }

                return AutomationTrigger::active()
                    ->where('trigger_type', 'manual')
                    ->where('model_class', get_class($record))
                    ->exists();
            });
    }
}
