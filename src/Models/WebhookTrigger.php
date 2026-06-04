<?php

namespace Ashrafic\FilamentWebhookBridge\Models;

use Ashrafic\FilamentWebhookBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Enums\PayloadMode;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebhookTrigger extends Model
{
    protected $table = 'webhook_triggers';

    protected $fillable = [
        'name',
        'description',
        'model_class',
        'event',
        'destination_type',
        'destination_url',
        'field_mapping',
        'payload_mode',
        'custom_payload_template',
        'conditions',
        'secret',
        'active',
        'webhook_timeout',
        'max_retries',
        'ip_whitelist',
        'encrypt_payload',
        'created_by',
    ];

    protected $casts = [
        'field_mapping' => 'array',
        'conditions' => 'array',
        'ip_whitelist' => 'array',
        'active' => 'boolean',
        'encrypt_payload' => 'boolean',
        'webhook_timeout' => 'integer',
        'max_retries' => 'integer',
        'event' => EventEnum::class,
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
        return $this->hasMany(WebhookDelivery::class, 'trigger_id');
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
            Cache::forget("webhook_bridge.triggers.{$trigger->model_class}.{$trigger->event->value}");
        });

        static::deleted(function (self $trigger) {
            Cache::forget("webhook_bridge.triggers.{$trigger->model_class}.{$trigger->event->value}");
        });
    }
}
