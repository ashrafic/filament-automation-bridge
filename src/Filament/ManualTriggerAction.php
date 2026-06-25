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
            ->label(__('filament-automation-bridge::actions.send_automation'))
            ->color('success')
            ->form(fn () => [
                Select::make('trigger_id')
                    ->label(__('filament-automation-bridge::actions.select_automation_trigger'))
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
                    ->title($delivery ? __('filament-automation-bridge::notifications.automation_sent') : __('filament-automation-bridge::notifications.automation_queued'))
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
