<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Unit\Models;

use Ashrafic\FilamentWebhookBridge\Enums\DeliverySource;
use Ashrafic\FilamentWebhookBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Enums\PayloadMode;
use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    protected function createTrigger(array $overrides = []): WebhookTrigger
    {
        return WebhookTrigger::create(array_merge([
            'name' => 'Test Trigger',
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Custom,
            'destination_url' => 'https://example.com/webhook',
            'field_mapping' => ['name', 'email'],
            'payload_mode' => PayloadMode::Summary,
            'active' => true,
            'max_retries' => 3,
            'webhook_timeout' => 5,
        ], $overrides));
    }

    protected function createDelivery(array $overrides = []): WebhookDelivery
    {
        $trigger = $overrides['trigger_id'] ?? null;
        if (! $trigger instanceof WebhookTrigger) {
            $trigger = $this->createTrigger();
        }

        return WebhookDelivery::create(array_merge([
            'trigger_id' => $trigger->id,
            'model_type' => 'App\\Models\\User',
            'model_id' => 1,
            'payload' => ['event' => 'created', 'data' => ['id' => 1]],
            'status' => DeliveryStatus::Pending,
            'retry_count' => 0,
            'max_retries' => 3,
            'source' => DeliverySource::Realtime,
        ], $overrides));
    }

    public function test_generates_uuid_on_creation(): void
    {
        $delivery = $this->createDelivery();

        $this->assertNotNull($delivery->uuid);
        $this->assertSame(36, strlen($delivery->uuid));
    }

    public function test_does_not_override_existing_uuid(): void
    {
        $existingUuid = '550e8400-e29b-41d4-a716-446655440000';

        $delivery = $this->createDelivery(['uuid' => $existingUuid]);

        $this->assertSame($existingUuid, $delivery->uuid);
    }

    public function test_belongs_to_trigger_relationship(): void
    {
        $trigger = $this->createTrigger();
        $delivery = $this->createDelivery(['trigger_id' => $trigger->id]);

        $this->assertInstanceOf(WebhookTrigger::class, $delivery->trigger);
        $this->assertSame($trigger->id, $delivery->trigger->id);
    }

    public function test_can_retry_returns_true_for_failed_under_max(): void
    {
        $delivery = $this->createDelivery([
            'status' => DeliveryStatus::Failed,
            'retry_count' => 1,
            'max_retries' => 3,
        ]);

        $this->assertTrue($delivery->canRetry());
    }

    public function test_can_retry_returns_false_when_cancelled(): void
    {
        $delivery = $this->createDelivery([
            'status' => DeliveryStatus::Cancelled,
            'retry_count' => 0,
            'max_retries' => 3,
        ]);

        $this->assertFalse($delivery->canRetry());
    }

    public function test_can_retry_returns_false_when_pending(): void
    {
        $delivery = $this->createDelivery([
            'status' => DeliveryStatus::Pending,
            'retry_count' => 0,
            'max_retries' => 3,
        ]);

        $this->assertFalse($delivery->canRetry());
    }

    public function test_can_retry_returns_false_at_max_retries(): void
    {
        $delivery = $this->createDelivery([
            'status' => DeliveryStatus::Failed,
            'retry_count' => 3,
            'max_retries' => 3,
        ]);

        $this->assertFalse($delivery->canRetry());
    }

    public function test_mark_dispatched_sets_status_and_time(): void
    {
        $delivery = $this->createDelivery(['status' => DeliveryStatus::Pending]);

        $delivery->markDispatched();

        $this->assertSame(DeliveryStatus::Pending, $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->dispatched_at);
    }

    public function test_mark_success_stores_response_and_status(): void
    {
        $delivery = $this->createDelivery();

        $delivery->markSuccess(200, ['Content-Type' => 'application/json'], '{"ok": true}', 150);

        $fresh = $delivery->fresh();
        $this->assertSame(DeliveryStatus::Success, $fresh->status);
        $this->assertSame(200, $fresh->http_status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertSame(150, $fresh->duration_ms);
    }

    public function test_mark_success_truncates_long_response_body(): void
    {
        $delivery = $this->createDelivery();
        $longBody = str_repeat('a', 20000);

        $delivery->markSuccess(200, [], $longBody, 100);

        $fresh = $delivery->fresh();
        $this->assertLessThanOrEqual(10243, strlen($fresh->response_body));
    }

    public function test_mark_failed_stores_error_details(): void
    {
        $delivery = $this->createDelivery();

        $delivery->markFailed(2, 500, 'Internal Server Error', 300);

        $fresh = $delivery->fresh();
        $this->assertSame(DeliveryStatus::Failed, $fresh->status);
        $this->assertSame(2, $fresh->retry_count);
        $this->assertSame(500, $fresh->http_status);
        $this->assertSame('Internal Server Error', $fresh->error_message);
        $this->assertSame(300, $fresh->duration_ms);
        $this->assertNotNull($fresh->completed_at);
    }
}
