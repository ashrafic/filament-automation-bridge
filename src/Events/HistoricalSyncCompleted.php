<?php

namespace Ashrafic\FilamentAutomationBridge\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HistoricalSyncCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $batchUuid,
        public int $total,
        public int $successful,
        public int $failed,
    ) {}
}
