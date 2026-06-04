<?php

namespace Ashrafic\FilamentWebhookBridge\Enums;

enum DestinationType: string
{
    case Zapier = 'zapier';
    case Make = 'make';
    case N8n = 'n8n';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Zapier => 'Zapier',
            self::Make => 'Make',
            self::N8n => 'n8n',
            self::Custom => 'Custom',
        };
    }
}