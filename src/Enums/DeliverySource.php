<?php

namespace Ashrafic\FilamentWebhookBridge\Enums;

enum DeliverySource: string
{
    case Realtime = 'realtime';
    case HistoricalSync = 'historical_sync';
    case Test = 'test';
    case ManualRetry = 'manual_retry';

    public function getLabel(): string
    {
        return match ($this) {
            self::Realtime => 'Realtime',
            self::HistoricalSync => 'Historical Sync',
            self::Test => 'Test',
            self::ManualRetry => 'Manual Retry',
        };
    }
}