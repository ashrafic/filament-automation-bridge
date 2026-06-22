<?php

namespace Ashrafic\FilamentAutomationBridge\Commands;

use Ashrafic\FilamentAutomationBridge\Services\TemplateManager;
use Illuminate\Console\Command;

class SeedTemplatesCommand extends Command
{
    protected $signature = 'automation-bridge:seed';

    protected $description = 'Seed the built-in automation templates';

    public function handle(): int
    {
        $this->info('Seeding built-in templates...');

        try {
            app(TemplateManager::class)->seedBuiltins();
            $this->info('Built-in templates seeded successfully.');
        } catch (\Throwable $e) {
            $this->warn("Could not seed templates: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
