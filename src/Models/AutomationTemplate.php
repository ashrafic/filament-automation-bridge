<?php

namespace Ashrafic\FilamentAutomationBridge\Models;

use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Illuminate\Database\Eloquent\Model;

class AutomationTemplate extends Model
{
    protected $table = 'automation_templates';

    protected $fillable = [
        'name',
        'description',
        'is_builtin',
        'model_class',
        'event',
        'destination_type',
        'field_mapping',
        'payload_mode',
        'custom_payload_template',
        'conditions',
        'created_by',
    ];

    protected $casts = [
        'is_builtin' => 'boolean',
        'field_mapping' => 'array',
        'conditions' => 'array',
        'event' => EventEnum::class,
        'destination_type' => DestinationType::class,
        'payload_mode' => PayloadMode::class,
    ];

    public function toTrigger(array $overrides = []): AutomationTrigger
    {
        $defaults = [
            'name' => $this->name,
            'description' => $this->description,
            'model_class' => $this->model_class,
            'event' => $this->event,
            'destination_type' => $this->destination_type,
            'field_mapping' => $this->field_mapping,
            'payload_mode' => $this->payload_mode,
            'custom_payload_template' => $this->custom_payload_template,
            'conditions' => $this->conditions,
        ];

        return new AutomationTrigger(array_merge($defaults, $overrides));
    }
}
