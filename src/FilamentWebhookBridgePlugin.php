<?php

namespace Ashrafic\FilamentWebhookBridge;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentWebhookBridgePlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-webhook-bridge';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->discoverResources(
                in: __DIR__ . '/Filament/Resources',
                for: 'Ashrafic\\FilamentWebhookBridge\\Filament\\Resources',
            )
            ->discoverPages(
                in: __DIR__ . '/Filament/Pages',
                for: 'Ashrafic\\FilamentWebhookBridge\\Filament\\Pages',
            )
            ->discoverWidgets(
                in: __DIR__ . '/Filament/Widgets',
                for: 'Ashrafic\\FilamentWebhookBridge\\Filament\\Widgets',
            );
    }

    public function boot(Panel $panel): void
    {
    }

    public static function make(): static
    {
        return app(static::class);
    }
}