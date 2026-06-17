<?php

namespace Ashrafic\FilamentAutomationBridge;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentAutomationBridgePlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-automation-bridge';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->discoverResources(
                in: __DIR__.'/Filament/Resources',
                for: 'Ashrafic\\FilamentAutomationBridge\\Filament\\Resources',
            )
            ->discoverPages(
                in: __DIR__.'/Filament/Pages',
                for: 'Ashrafic\\FilamentAutomationBridge\\Filament\\Pages',
            )
            ->discoverWidgets(
                in: __DIR__.'/Filament/Widgets',
                for: 'Ashrafic\\FilamentAutomationBridge\\Filament\\Widgets',
            );
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
