<?php

namespace Ashrafic\FilamentWebhookBridge\Filament\Resources\Pages;

use Ashrafic\FilamentWebhookBridge\Filament\Resources\WebhookTriggerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWebhookTrigger extends CreateRecord
{
    protected static string $resource = WebhookTriggerResource::class;
}
