<?php

namespace Ashrafic\FilamentAutomationBridge\Services;

use Ashrafic\FilamentAutomationBridge\Models\AutomationTemplate;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Illuminate\Support\Collection;

class TemplateManager
{
    public function getAll(): Collection
    {
        return AutomationTemplate::orderBy('name')->get();
    }

    public function saveFromTrigger(AutomationTrigger $trigger, string $name, ?string $description = null): AutomationTemplate
    {
        return AutomationTemplate::create([
            'name' => $name,
            'description' => $description,
            'model_class' => $trigger->model_class,
            'event' => $trigger->event,
            'destination_type' => $trigger->destination_type,
            'field_mapping' => $trigger->field_mapping ?? [],
            'payload_mode' => $trigger->payload_mode,
            'custom_payload_template' => $trigger->custom_payload_template,
            'conditions' => $trigger->conditions,
        ]);
    }

    public function applyTemplate(AutomationTemplate $template): array
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
