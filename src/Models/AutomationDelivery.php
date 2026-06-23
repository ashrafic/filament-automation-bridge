<?php

namespace Ashrafic\FilamentAutomationBridge\Models;

use Ashrafic\FilamentAutomationBridge\Enums\DeliverySource;
use Ashrafic\FilamentAutomationBridge\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class AutomationDelivery extends Model
{
    protected $table = 'automation_deliveries';

    protected $fillable = [
        'uuid',
        'trigger_id',
        'model_type',
        'model_id',
        'payload',
        'headers',
        'response_headers',
        'status',
        'http_status',
        'response_body',
        'retry_count',
        'max_retries',
        'source',
        'error_message',
        'duration_ms',
        'dispatched_at',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'response_headers' => 'array',
        'status' => DeliveryStatus::class,
        'source' => DeliverySource::class,
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'http_status' => 'integer',
        'duration_ms' => 'integer',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $delivery) {
            if (empty($delivery->uuid)) {
                $delivery->uuid = Str::uuid()->toString();
            }
        });
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(AutomationTrigger::class, 'trigger_id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function canRetry(): bool
    {
        if ($this->status === DeliveryStatus::Cancelled) {
            return false;
        }

        if ($this->status === DeliveryStatus::Pending) {
            return false;
        }

        return $this->retry_count < $this->max_retries;
    }

    public function markDispatched(): void
    {
        $this->update([
            'status' => DeliveryStatus::Pending,
            'dispatched_at' => now(),
        ]);
    }

    public function markSuccess(int $httpStatus, ?array $responseHeaders, ?string $responseBody, int $durationMs): void
    {
        $this->update([
            'status' => DeliveryStatus::Success,
            'http_status' => $httpStatus,
            'response_headers' => $responseHeaders,
            'response_body' => $responseBody ? Str::limit($responseBody, 10240) : null,
            'duration_ms' => $durationMs,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(int $retryCount, ?int $httpStatus, ?string $errorMessage, ?int $durationMs): void
    {
        $this->update([
            'status' => DeliveryStatus::Failed,
            'retry_count' => $retryCount,
            'http_status' => $httpStatus,
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
            'completed_at' => now(),
        ]);
    }
}
