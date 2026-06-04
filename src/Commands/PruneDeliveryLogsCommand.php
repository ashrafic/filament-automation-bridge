<?php

namespace Ashrafic\FilamentWebhookBridge\Commands;

use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Illuminate\Console\Command;

class PruneDeliveryLogsCommand extends Command
{
    protected $signature = 'webhook-bridge:prune-logs
        {--days= : Number of days to keep logs (default from config)}
        {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Prune old webhook delivery logs';

    public function handle(): int
    {
        $days = $this->option('days')
            ? (int) $this->option('days')
            : config('filament-webhook-bridge.retention.delivery_logs_days', 90);

        $cutoff = now()->subDays($days);

        $query = WebhookDelivery::where('created_at', '<', $cutoff);

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
