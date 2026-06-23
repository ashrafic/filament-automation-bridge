<?php

namespace Ashrafic\FilamentAutomationBridge\Commands;

use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Illuminate\Console\Command;

class TestConnectionCommand extends Command
{
    protected $signature = 'automation-bridge:test
        {triggerId : The ID of the trigger to test}';

    protected $description = 'Test a automation trigger connection';

    public function handle(): int
    {
        $triggerId = $this->argument('triggerId');

        $trigger = AutomationTrigger::find($triggerId);

        if (! $trigger) {
            $this->error("automation trigger with ID {$triggerId} not found.");

            return self::FAILURE;
        }

        $this->info("Testing connection for trigger [{$trigger->name}] (ID: {$triggerId})...");
        $this->line("  Destination: {$trigger->destination_url}");

        try {
            $result = app(DeliveryService::class)->testConnection($trigger);

            $this->newLine();

            if ($result['success']) {
                $this->info('Connection successful!');
            } else {
                $this->error('Connection failed!');
            }

            if ($result['http_status']) {
                $this->line("  HTTP Status: {$result['http_status']}");
            }

            if ($result['duration_ms']) {
                $this->line("  Response Time: {$result['duration_ms']} ms");
            }

            if ($result['error']) {
                $this->line("  Error: {$result['error']}");
            }

            if ($result['response_body']) {
                $this->line('  Response Body:');
                $this->line(str_repeat(' ', 4).substr($result['response_body'], 0, 500));
            }

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error("Test failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
