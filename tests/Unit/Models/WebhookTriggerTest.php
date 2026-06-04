<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Unit\Models;

use Ashrafic\FilamentWebhookBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Enums\PayloadMode;
use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class WebhookTriggerTest extends TestCase
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

    public function test_encrypts_secret_on_save_and_decrypts_on_read(): void
    {
        $trigger = $this->createTrigger(['secret' => 'my-secret-key']);

        $this->assertNotSame('my-secret-key', $trigger->getRawOriginal('secret'));
        $this->assertSame('my-secret-key', $trigger->secret);
    }

    public function test_secret_attribute_returns_null_when_not_set(): void
    {
        $trigger = $this->createTrigger(['secret' => null]);

        $this->assertNull($trigger->secret);
    }

    public function test_casts_event_enum_correctly(): void
    {
        $trigger = $this->createTrigger(['event' => EventEnum::Updated]);

        $this->assertInstanceOf(EventEnum::class, $trigger->event);
        $this->assertSame(EventEnum::Updated, $trigger->event);
    }

    public function test_casts_destination_type_enum_correctly(): void
    {
        $trigger = $this->createTrigger(['destination_type' => DestinationType::Zapier]);

        $this->assertInstanceOf(DestinationType::class, $trigger->destination_type);
        $this->assertSame(DestinationType::Zapier, $trigger->destination_type);
    }

    public function test_casts_payload_mode_enum_correctly(): void
    {
        $trigger = $this->createTrigger(['payload_mode' => PayloadMode::All]);

        $this->assertInstanceOf(PayloadMode::class, $trigger->payload_mode);
        $this->assertSame(PayloadMode::All, $trigger->payload_mode);
    }

    public function test_casts_json_fields_to_array(): void
    {
        $trigger = $this->createTrigger([
            'field_mapping' => ['name', 'email'],
            'conditions' => [['field' => 'status', 'operator' => 'equals', 'value' => 'active']],
            'ip_whitelist' => ['192.168.1.1'],
        ]);

        $this->assertIsArray($trigger->field_mapping);
        $this->assertIsArray($trigger->conditions);
        $this->assertIsArray($trigger->ip_whitelist);
        $this->assertSame(['name', 'email'], $trigger->field_mapping);
    }

    public function test_casts_boolean_fields(): void
    {
        $trigger = $this->createTrigger(['active' => true, 'encrypt_payload' => false]);

        $this->assertIsBool($trigger->active);
        $this->assertIsBool($trigger->encrypt_payload);
        $this->assertTrue($trigger->active);
        $this->assertFalse($trigger->encrypt_payload);
    }

    public function test_generates_auto_secret(): void
    {
        $secret1 = WebhookTrigger::generateSecret();
        $secret2 = WebhookTrigger::generateSecret();

        $this->assertSame(64, strlen($secret1));
        $this->assertNotSame($secret1, $secret2);
    }

    public function test_scope_active_returns_only_active_triggers(): void
    {
        $this->createTrigger(['name' => 'Active', 'active' => true]);
        $this->createTrigger(['name' => 'Inactive', 'active' => false]);

        $active = WebhookTrigger::active()->get();

        $this->assertCount(1, $active);
        $this->assertSame('Active', $active->first()->name);
    }

    public function test_scope_for_model_event_filters_correctly(): void
    {
        $this->createTrigger([
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Created,
        ]);
        $this->createTrigger([
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Updated,
        ]);
        $this->createTrigger([
            'model_class' => 'App\\Models\\Order',
            'event' => EventEnum::Created,
        ]);

        $results = WebhookTrigger::forModelEvent('App\\Models\\User', EventEnum::Created)->get();

        $this->assertCount(1, $results);
        $this->assertSame('App\\Models\\User', $results->first()->model_class);
        $this->assertSame(EventEnum::Created, $results->first()->event);
    }

    public function test_success_rate_calculation(): void
    {
        $trigger = $this->createTrigger();

        WebhookDelivery::create([
            'trigger_id' => $trigger->id,
            'model_type' => 'App\\Models\\User',
            'model_id' => 1,
            'payload' => [],
            'status' => DeliveryStatus::Success,
            'retry_count' => 0,
            'max_retries' => 3,
            'source' => 'realtime',
        ]);
        WebhookDelivery::create([
            'trigger_id' => $trigger->id,
            'model_type' => 'App\\Models\\User',
            'model_id' => 2,
            'payload' => [],
            'status' => DeliveryStatus::Failed,
            'retry_count' => 1,
            'max_retries' => 3,
            'source' => 'realtime',
        ]);

        $rates = $trigger->successRateLast7Days();

        $this->assertSame(1, $rates['success']);
        $this->assertSame(2, $rates['total']);
    }

    public function test_has_many_deliveries_relationship(): void
    {
        $trigger = $this->createTrigger();

        WebhookDelivery::create([
            'trigger_id' => $trigger->id,
            'model_type' => 'App\\Models\\User',
            'model_id' => 1,
            'payload' => [],
            'status' => DeliveryStatus::Success,
            'retry_count' => 0,
            'max_retries' => 3,
            'source' => 'realtime',
        ]);

        $this->assertCount(1, $trigger->deliveries);
        $this->assertInstanceOf(WebhookDelivery::class, $trigger->deliveries->first());
    }

    public function test_invalidates_cache_on_save(): void
    {
        Cache::put('webhook_bridge.triggers.App\\Models\\User.created', 'cached-value');

        $this->createTrigger([
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Created,
        ]);

        $this->assertNull(Cache::get('webhook_bridge.triggers.App\\Models\\User.created'));
    }

    public function test_invalidates_cache_on_delete(): void
    {
        $trigger = $this->createTrigger([
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Created,
        ]);

        Cache::put('webhook_bridge.triggers.App\\Models\\User.created', 'cached-value');

        $trigger->delete();

        $this->assertNull(Cache::get('webhook_bridge.triggers.App\\Models\\User.created'));
    }
}
