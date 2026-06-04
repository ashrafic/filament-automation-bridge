<?php

namespace Ashrafic\FilamentWebhookBridge\Services;

use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTemplate;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Illuminate\Support\Collection;

class TemplateManager
{
    public function getAll(): Collection
    {
        return WebhookTemplate::orderByDesc('is_builtin')
            ->orderBy('name')
            ->get();
    }

    public function getBuiltins(): Collection
    {
        return WebhookTemplate::where('is_builtin', true)
            ->orderBy('name')
            ->get();
    }

    public function seedBuiltins(): void
    {
        $builtins = [
            [
                'name' => 'New User Registered',
                'description' => 'Triggered when a new user registers in the system',
                'is_builtin' => true,
                'model_class' => 'App\Models\User',
                'event' => EventEnum::Created,
                'field_mapping' => ['id', 'name', 'email', 'created_at'],
                'destination_type' => DestinationType::Zapier,
                'payload_mode' => 'summary',
            ],
            [
                'name' => 'Order Placed',
                'description' => 'Triggered when a new paid order is placed',
                'is_builtin' => true,
                'model_class' => 'App\Models\Order',
                'event' => EventEnum::Created,
                'field_mapping' => ['id', 'total', 'status', 'customer.name', 'customer.email', 'items.*'],
                'conditions' => [['field' => 'status', 'operator' => 'equals', 'value' => 'paid', 'logic' => null]],
                'destination_type' => DestinationType::Make,
                'payload_mode' => 'summary',
            ],
            [
                'name' => 'Ticket Created',
                'description' => 'Triggered when a new support ticket is created',
                'is_builtin' => true,
                'model_class' => 'App\Models\SupportTicket',
                'event' => EventEnum::Created,
                'field_mapping' => ['id', 'subject', 'priority', 'user.email'],
                'destination_type' => DestinationType::N8n,
                'payload_mode' => 'summary',
            ],
            [
                'name' => 'Lead Captured',
                'description' => 'Triggered when a new lead is captured',
                'is_builtin' => true,
                'model_class' => 'App\Models\Lead',
                'event' => EventEnum::Created,
                'field_mapping' => ['id', 'name', 'email', 'source', 'created_at'],
                'destination_type' => DestinationType::Zapier,
                'payload_mode' => 'summary',
            ],
            [
                'name' => 'Invoice Paid',
                'description' => 'Triggered when an invoice status changes to paid',
                'is_builtin' => true,
                'model_class' => 'App\Models\Invoice',
                'event' => EventEnum::Updated,
                'field_mapping' => ['id', 'amount', 'paid_at', 'customer.email'],
                'conditions' => [['field' => 'status', 'operator' => 'changed_to', 'value' => 'paid', 'logic' => null]],
                'destination_type' => DestinationType::Make,
                'payload_mode' => 'summary',
            ],
            [
                'name' => 'User Deleted',
                'description' => 'Triggered when a user account is deleted',
                'is_builtin' => true,
                'model_class' => 'App\Models\User',
                'event' => EventEnum::Deleted,
                'field_mapping' => ['id', 'email', 'deleted_at'],
                'destination_type' => DestinationType::Custom,
                'payload_mode' => 'summary',
            ],
        ];

        foreach ($builtins as $template) {
            WebhookTemplate::firstOrCreate(
                ['name' => $template['name'], 'is_builtin' => true],
                $template,
            );
        }
    }

    public function saveFromTrigger(WebhookTrigger $trigger, string $name, ?string $description = null): WebhookTemplate
    {
        return WebhookTemplate::create([
            'name' => $name,
            'description' => $description,
            'is_builtin' => false,
            'model_class' => $trigger->model_class,
            'event' => $trigger->event,
            'destination_type' => $trigger->destination_type,
            'field_mapping' => $trigger->field_mapping,
            'payload_mode' => $trigger->payload_mode,
            'custom_payload_template' => $trigger->custom_payload_template,
            'conditions' => $trigger->conditions,
        ]);
    }

    public function applyTemplate(WebhookTemplate $template): array
    {
        return [
            'name' => $template->name,
            'description' => $template->description,
            'model_class' => $template->model_class,
            'event' => $template->event->value,
            'destination_type' => $template->destination_type->value,
            'field_mapping' => $template->field_mapping,
            'payload_mode' => $template->payload_mode->value,
            'custom_payload_template' => $template->custom_payload_template,
            'conditions' => $template->conditions,
        ];
    }
}
