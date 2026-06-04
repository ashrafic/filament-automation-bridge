<?php

namespace Ashrafic\FilamentWebhookBridge\Commands;

use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\DeliveryService;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ProcessScheduledTriggersCommand extends Command
{
    protected $signature = 'webhook-bridge:process-scheduled';

    protected $description = 'Process all active schedule-based webhook triggers';

    public function handle(): int
    {
        $triggers = WebhookTrigger::active()
            ->where('trigger_type', 'schedule')
            ->get();

        if ($triggers->isEmpty()) {
            $this->info('No active schedule triggers found.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        $skipped = 0;

        foreach ($triggers as $trigger) {
            $config = $trigger->trigger_config ?? [];
            $scheduleType = $config['schedule_type'] ?? 'daily';

            if (! $this->shouldRun($trigger, $scheduleType, $config)) {
                $skipped++;

                continue;
            }

            $modelClass = $trigger->model_class;

            if (! class_exists($modelClass)) {
                $this->warn("Model class [{$modelClass}] not found for trigger [{$trigger->name}].");

                continue;
            }

            $model = $this->getLatestModel($modelClass);

            if ($model === null) {
                $this->warn("No records found for model [{$modelClass}] on trigger [{$trigger->name}].");

                continue;
            }

            try {
                $delivery = app(DeliveryService::class)->dispatchForSchedule($trigger, $model);

                if ($delivery) {
                    $dispatched++;
                    $this->info("Dispatched schedule trigger [{$trigger->name}] (ID: {$trigger->id})");
                }
            } catch (\Throwable $e) {
                $this->error("Failed to dispatch trigger [{$trigger->name}]: {$e->getMessage()}");
            }

            $this->markRun($trigger, $scheduleType);
        }

        $this->info("Processed {$triggers->count()} schedule triggers. Dispatched: {$dispatched}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }

    protected function shouldRun(WebhookTrigger $trigger, string $scheduleType, array $config): bool
    {
        return match ($scheduleType) {
            'hourly' => $this->shouldRunHourly($trigger),
            'daily' => $this->shouldRunDaily($trigger),
            'weekly' => $this->shouldRunWeekly($trigger),
            'monthly' => $this->shouldRunMonthly($trigger),
            'custom' => $this->shouldRunCustom($config['custom_cron'] ?? '* * * * *'),
            default => true,
        };
    }

    protected function shouldRunHourly(WebhookTrigger $trigger): bool
    {
        $cacheKey = "webhook_bridge.schedule.last_run.{$trigger->id}.hourly";
        $lastRun = Cache::get($cacheKey);
        $currentHour = now()->format('Y-m-d H:00');

        if ($lastRun === $currentHour) {
            return false;
        }

        return true;
    }

    protected function shouldRunDaily(WebhookTrigger $trigger): bool
    {
        $cacheKey = "webhook_bridge.schedule.last_run.{$trigger->id}.daily";
        $lastRun = Cache::get($cacheKey);
        $today = now()->toDateString();

        if ($lastRun === $today) {
            return false;
        }

        return true;
    }

    protected function shouldRunWeekly(WebhookTrigger $trigger): bool
    {
        $cacheKey = "webhook_bridge.schedule.last_run.{$trigger->id}.weekly";
        $lastRun = Cache::get($cacheKey);
        $currentWeek = now()->format('Y-W');

        if ($lastRun === $currentWeek) {
            return false;
        }

        return true;
    }

    protected function shouldRunMonthly(WebhookTrigger $trigger): bool
    {
        $cacheKey = "webhook_bridge.schedule.last_run.{$trigger->id}.monthly";
        $lastRun = Cache::get($cacheKey);
        $currentMonth = now()->format('Y-m');

        if ($lastRun === $currentMonth) {
            return false;
        }

        return true;
    }

    protected function shouldRunCustom(string $cronExpression): bool
    {
        try {
            $cron = new CronExpression($cronExpression);

            return $cron->isDue();
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function markRun(WebhookTrigger $trigger, string $scheduleType): void
    {
        $period = match ($scheduleType) {
            'hourly' => now()->format('Y-m-d H:00'),
            'daily' => now()->toDateString(),
            'weekly' => now()->format('Y-W'),
            'monthly' => now()->format('Y-m'),
            'custom' => now()->toIso8601String(),
            default => now()->toIso8601String(),
        };

        Cache::put(
            "webhook_bridge.schedule.last_run.{$trigger->id}.{$scheduleType}",
            $period,
            now()->addDays(60),
        );
    }

    protected function getLatestModel(string $modelClass): ?Model
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        return $modelClass::query()->latest()->first();
    }
}
