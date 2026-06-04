<?php

namespace Ashrafic\FilamentWebhookBridge\Filament\Widgets;

use Ashrafic\FilamentWebhookBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\DeliveryService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class WebhookHealthWidget extends Widget
{
    protected static string $view = 'filament-webhook-bridge::widgets.webhook-health';

    protected static ?int $sort = 80;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $activeTriggers = WebhookTrigger::where('active', true)->count();

        $deliveries24h = WebhookDelivery::where('created_at', '>=', now()->subDay())->count();

        $successful24h = WebhookDelivery::where('created_at', '>=', now()->subDay())
            ->where('status', DeliveryStatus::Success)
            ->count();

        $successRate = $deliveries24h > 0
            ? round(($successful24h / $deliveries24h) * 100, 1)
            : null;

        $failedNeedsAttention = WebhookDelivery::where('status', DeliveryStatus::Failed)
            ->where('created_at', '>=', now()->subDay())
            ->whereColumn('retry_count', '>=', 'max_retries')
            ->count();

        $recentFailures = WebhookDelivery::with('trigger')
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
        $delivery = WebhookDelivery::find($deliveryId);

        if (! $delivery || ! $delivery->canRetry()) {
            Notification::make()
                ->title('Cannot retry this delivery')
                ->warning()
                ->send();

            return;
        }

        try {
            app(DeliveryService::class)->retry($delivery);

            Notification::make()
                ->title('Delivery retry queued')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Retry failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
