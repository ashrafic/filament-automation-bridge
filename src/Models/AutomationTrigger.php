<?php

namespace Ashrafic\FilamentAutomationBridge\Models;

use Ashrafic\FilamentAutomationBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Events\TriggerActivated;
use Ashrafic\FilamentAutomationBridge\Events\TriggerChanged;
use Ashrafic\FilamentAutomationBridge\Events\TriggerDeactivated;
use Ashrafic\FilamentAutomationBridge\Triggers\TriggerManager;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AutomationTrigger extends Model
{
    protected $table = 'automation_triggers';

    protected $fillable = [
        'name',
        'description',
        'model_class',
        'event',
        'trigger_type',
        'trigger_config',
        'destination_type',
        'destination_url',
        'field_mapping',
        'payload_mode',
        'custom_payload_template',
        'conditions',
        'secret',
        'active',
        'request_timeout',
        'max_retries',
        'ip_whitelist',
        'encrypt_payload',
        'created_by',
    ];

    protected $casts = [
        'field_mapping' => 'array',
        'conditions' => 'array',
        'ip_whitelist' => 'array',
        'trigger_config' => 'array',
        'active' => 'boolean',
        'encrypt_payload' => 'boolean',
        'request_timeout' => 'integer',
        'max_retries' => 'integer',
        'event' => EventEnum::class,
        'trigger_type' => 'string',
        'destination_type' => DestinationType::class,
        'payload_mode' => PayloadMode::class,
    ];

    protected function secret(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? decrypt($value) : null,
            set: fn (?string $value) => $value ? encrypt($value) : null,
        );
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(AutomationDelivery::class, 'trigger_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForModelEvent($query, string $modelClass, EventEnum $event)
    {
        return $query->where('model_class', $modelClass)
            ->where('event', $event->value);
    }

    public static function generateSecret(): string
    {
        return hash('sha256', Str::random(64));
    }

    public function successRateLast7Days(): array
    {
        $deliveries = $this->deliveries()
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        $total = $deliveries->count();
        $success = $deliveries->where('status', DeliveryStatus::Success)->count();

        return [
            'success' => $success,
            'total' => $total,
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $trigger) {
            if ($trigger->event !== null) {
                Cache::forget("automation_bridge.triggers.{$trigger->model_class}.{$trigger->event->value}");
            }

            $action = $trigger->wasRecentlyCreated ? 'created' : 'updated';

            event(new TriggerChanged($trigger, $action));

            if ($trigger->wasChanged('active')) {
                if ($trigger->active) {
                    event(new TriggerActivated($trigger));
                } else {
                    event(new TriggerDeactivated($trigger));
                }
            }

            if ($trigger->trigger_type !== null && $trigger->active && ! $trigger->isTriggerType('model-event')) {
                app(TriggerManager::class)->subscribe($trigger);
            }
        });

        static::deleted(function (self $trigger) {
            if ($trigger->event !== null) {
                Cache::forget("automation_bridge.triggers.{$trigger->model_class}.{$trigger->event->value}");
            }

            event(new TriggerChanged($trigger, 'deleted'));

            if ($trigger->trigger_type !== null && ! $trigger->isTriggerType('model-event')) {
                app(TriggerManager::class)->unsubscribe($trigger);
            }
        });
    }

    public function isTriggerType(string $type): bool
    {
        return $this->trigger_type === $type;
    }
}
