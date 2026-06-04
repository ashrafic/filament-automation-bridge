<?php

namespace Ashrafic\FilamentWebhookBridge\Filament\Resources\Pages;

use Ashrafic\FilamentWebhookBridge\Filament\Resources\WebhookTriggerResource;
use Filament\Resources\Pages\ListRecords;

class ListWebhookTriggers extends ListRecords
{
    protected static string $resource = WebhookTriggerResource::class;
}
