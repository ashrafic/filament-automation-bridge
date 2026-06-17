<?php

namespace Ashrafic\FilamentAutomationBridge\Commands;

use Ashrafic\FilamentAutomationBridge\Services\TemplateManager;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'automation-bridge:install';

    protected $description = 'Install the Filament Automation Bridge plugin';

    public function handle(): int
    {
        $this->info('Installing Filament Automation Bridge...');

        $this->call('vendor:publish', [
            '--tag' => 'filament-automation-bridge-config',
        ]);

        $this->call('migrate');

        $this->info('Seeding built-in templates...');

        try {
            app(TemplateManager::class)->seedBuiltins();
            $this->info('Built-in templates seeded successfully.');
        } catch (\Throwable $e) {
            $this->warn("Could not seed templates: {$e->getMessage()}");
        }

        $this->newLine();
        $this->info('Filament Automation Bridge installed successfully!');
        $this->newLine();
        $this->line('<fg=yellow>Next steps:</>');
        $this->line('  1. Add the plugin to your PanelProvider:');
        $this->line('     ->plugin(\\Ashrafic\\FilamentAutomationBridge\\FilamentAutomationBridgePlugin::make())');
        $this->line('  2. Start a queue worker for automation delivery:');
        $this->line('     php artisan queue:work --queue=webhooks');

        return self::SUCCESS;
    }
}
