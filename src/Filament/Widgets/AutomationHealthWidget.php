<?php

namespace Ashrafic\FilamentAutomationBridge\Filament\Widgets;

use Ashrafic\FilamentAutomationBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class AutomationHealthWidget extends Widget
{
    protected string $view = 'filament-automation-bridge::widgets.automation-health';

    protected static ?int $sort = 80;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $activeTriggers = AutomationTrigger::where('active', true)->count();

        $deliveries24h = AutomationDelivery::where('created_at', '>=', now()->subDay())->count();

        $successful24h = AutomationDelivery::where('created_at', '>=', now()->subDay())
            ->where('status', DeliveryStatus::Success)
            ->count();

        $successRate = $deliveries24h > 0
            ? round(($successful24h / $deliveries24h) * 100, 1)
            : null;

        $failedNeedsAttention = AutomationDelivery::where('status', DeliveryStatus::Failed)
            ->where('created_at', '>=', now()->subDay())
            ->whereColumn('retry_count', '>=', 'max_retries')
            ->count();

        $recentFailures = AutomationDelivery::with('trigger')
            ->where('status', DeliveryStatus::Failed)
            ->latest()
            ->limit(5)
            ->get();

        return [
            'activeTriggers' => $activeTriggers,
            'deliveries24h' => $deliveries24h,
            'successRate' => $successRate,
            'failedNeedsAttention' => $failedNeedsAttention,
            'recentFailures' => $recentFailures,
        ];
    }

    public function retryDelivery(int $deliveryId): void
    {
        $delivery = AutomationDelivery::find($deliveryId);

        if (! $delivery || ! $delivery->canRetry()) {
            Notification::make()
                ->title(__('filament-automation-bridge::notifications.cannot_retry'))
                ->warning()
                ->send();

            return;
        }

        try {
            app(DeliveryService::class)->retry($delivery);

            Notification::make()
                ->title(__('filament-automation-bridge::notifications.retry_queued'))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('filament-automation-bridge::notifications.retry_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
