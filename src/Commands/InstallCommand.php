<?php

namespace Ashrafic\FilamentAutomationBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'automation-bridge:install';

    protected $description = 'Install the Filament Automation Bridge plugin';

    public function handle(): int
    {
        $this->info('Installing Filament Automation Bridge...');
        $this->newLine();

        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'filament-automation-bridge-config',
        ]);

        $this->info('Publishing migrations...');
        $this->publishMigrations();

        $this->newLine();
        $this->info('Filament Automation Bridge installed successfully!');
        $this->newLine();
        $this->line('<fg=yellow>Next steps:</>');
        $this->line('  1. Run the migrations:');
        $this->line('     php artisan migrate');
        $this->line('  2. Seed the built-in templates:');
        $this->line('     php artisan automation-bridge:seed');
        $this->line('  3. Add the plugin to your PanelProvider:');
        $this->line('     ->plugin(\\Ashrafic\\FilamentAutomationBridge\\FilamentAutomationBridgePlugin::make())');
        $this->line('  4. Start a queue worker for automation delivery:');
        $this->line('     php artisan queue:work --queue=webhooks');

        return self::SUCCESS;
    }

    protected function publishMigrations(): void
    {
        $source = realpath(__DIR__.'/../../database/migrations');

        if (! $source || ! is_dir($source)) {
            $this->warn("Could not locate migration source directory at: {$source}");

            return;
        }

        $target = database_path('migrations');

        File::ensureDirectoryExists($target);

        $migrations = File::files($source);

        foreach ($migrations as $migration) {
            $filename = $migration->getFilename();

            if ($filename === '.gitkeep') {
                continue;
            }

            $targetPath = $target.'/'.$filename;

            if (File::exists($targetPath)) {
                $this->line("   <fg=gray>Migration [{$filename}] already exists, skipped.</>");

                continue;
            }

            File::copy($migration->getPathname(), $targetPath);
            $this->line("   <fg=green>Copied: {$filename}</>");
        }
    }
}
