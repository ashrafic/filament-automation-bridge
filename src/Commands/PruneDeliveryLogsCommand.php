<?php

namespace Ashrafic\FilamentAutomationBridge\Commands;

use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use Illuminate\Console\Command;

class PruneDeliveryLogsCommand extends Command
{
    protected $signature = 'automation-bridge:prune-logs
        {--days= : Number of days to keep logs (default from config)}
        {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Prune old automation delivery logs';

    public function handle(): int
    {
        $days = $this->option('days')
            ? (int) $this->option('days')
            : config('filament-automation-bridge.retention.delivery_logs_days', 90);

        $cutoff = now()->subDays($days);

        $query = AutomationDelivery::where('created_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No delivery logs older than '.$days.' days found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] {$count} delivery log(s) would be deleted (older than {$days} days).");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("{$deleted} delivery log(s) pruned (older than {$days} days).");

        return self::SUCCESS;
    }
}
