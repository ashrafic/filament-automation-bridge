<?php

namespace Ashrafic\FilamentWebhookBridge\Enums;

enum PayloadMode: string
{
    case Summary = 'summary';
    case All = 'all';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Summary => 'Summary (Selected Fields)',
            self::All => 'All Fields',
            self::Custom => 'Custom Template',
        };
    }
}
