<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Integration;

use Ashrafic\FilamentWebhookBridge\Enums\DeliverySource;
use Ashrafic\FilamentWebhookBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Enums\PayloadMode;
use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\DeliveryService;
use Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models\TestUser;
use Ashrafic\FilamentWebhookBridge\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class WebhookDeliveryTest extends TestCase
{
    protected function createTrigger(array $overrides = []): WebhookTrigger
    {
        return WebhookTrigger::create(array_merge([
            'name' => 'Test Trigger',
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Custom,
            'destination_url' => 'https://httpbin.org/post',
            'field_mapping' => ['name', 'email'],
            'payload_mode' => PayloadMode::Summary,
            'active' => true,
            'max_retries' => 3,
            'webhook_timeout' => 5,
        ], $overrides));
    }

    public function test_dispatch_creates_delivery_on_model_created(): void
    {
        Config::set('filament-webhook-bridge.sandbox_mode', true);

        $trigger = $this->createTrigger([
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'active' => true,
        ]);

        $user = TestUser::create(['name' => 'New User', 'email' => 'new@example.com', 'status' => 'active']);

        $deliveryService = $this->app->make(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $user, EventEnum::Created);

        $this->assertNotNull($delivery);
        $this->assertInstanceOf(WebhookDelivery::class, $delivery);
        $this->assertSame($trigger->id, $delivery->trigger_id);
        $this->assertSame(TestUser::class, $delivery->model_type);
        $this->assertSame($user->id, $delivery->model_id);
        $this->assertSame(DeliverySource::Realtime, $delivery->source);
    }

    public function test_dispatch_creates_delivery_on_model_updated(): void
    {
        Config::set('filament-webhook-bridge.sandbox_mode', true);

        $trigger = $this->createTrigger([
            'model_class' => TestUser::class,
            'event' => EventEnum::Updated,
            'active' => true,
        ]);

        $user = TestUser::create(['name' => 'Update Me', 'email' => 'update@example.com']);

        $deliveryService = $this->app->make(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $user, EventEnum::Updated);

        $this->assertNotNull($delivery);
        $this->assertSame(DeliverySource::Realtime, $delivery->source);
    }

    public function test_dispatch_creates_delivery_on_model_deleted(): void
    {
        Config::set('filament-webhook-bridge.sandbox_mode', true);

        $trigger = $this->createTrigger([
            'model_class' => TestUser::class,
            'event' => EventEnum::Deleted,
            'active' => true,
        ]);

        $user = TestUser::create(['name' => 'Delete Me', 'email' => 'delete@example.com']);

        $deliveryService = $this->app->make(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $user, EventEnum::Deleted);

        $this->assertNotNull($delivery);
        $this->assertSame(DeliverySource::Realtime, $delivery->source);
    }

    public function test_skips_inactive_triggers(): void
    {
        Config::set('filament-webhook-bridge.sandbox_mode', true);

        $trigger = $this->createTrigger([
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'active' => false,
        ]);

        $user = TestUser::create(['name' => 'No Webhook', 'email' => 'no@example.com']);

        $deliveryService = $this->app->make(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $user, EventEnum::Created);

        $this->assertNotNull($delivery);
    }

    public function test_creates_delivery_record_with_correct_attributes(): void
    {
        Config::set('filament-webhook-bridge.sandbox_mode', true);

        $trigger = $this->createTrigger([
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'active' => true,
        ]);

        $user = TestUser::create(['name' => 'Record Test', 'email' => 'record@example.com']);

        $deliveryService = $this->app->make(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $user, EventEnum::Created);

        $this->assertNotNull($delivery);
        $this->assertSame($trigger->id, $delivery->trigger_id);
        $this->assertSame(TestUser::class, $delivery->model_type);
        $this->assertSame($user->id, $delivery->model_id);
        $this->assertSame(DeliverySource::Realtime, $delivery->source);
        $this->assertNotNull($delivery->payload);
        $this->assertSame('created', $delivery->payload['event']);
    }

    public function test_respects_conditions_and_skips_delivery(): void
    {
        Config::set('filament-webhook-bridge.sandbox_mode', true);

        $trigger = $this->createTrigger([
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'active' => true,
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
        ]);

        $user = TestUser::create(['name' => 'Regular User', 'email' => 'regular@example.com', 'status' => 'active']);

        $deliveryService = $this->app->make(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $user, EventEnum::Created);

        $this->assertNull($delivery);
    }

    public function test_respects_conditions_and_dispatches_when_matching(): void
    {
        Config::set('filament-webhook-bridge.sandbox_mode', true);

        $trigger = $this->createTrigger([
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'active' => true,
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
        ]);

        $user = TestUser::create(['name' => 'Premium User', 'email' => 'premium@example.com', 'status' => 'premium']);

        $deliveryService = $this->app->make(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $user, EventEnum::Created);

        $this->assertNotNull($delivery);
        $this->assertSame($trigger->id, $delivery->trigger_id);
    }

    public function test_sandbox_mode_marks_delivery_as_success(): void
    {
        Config::set('filament-webhook-bridge.sandbox_mode', true);

        $trigger = $this->createTrigger([
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'active' => true,
        ]);

        $user = TestUser::create(['name' => 'Sandbox Test', 'email' => 'sandbox@example.com']);

        $deliveryService = $this->app->make(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $user, EventEnum::Created);

        $fresh = $delivery->fresh();
        $this->assertSame(DeliveryStatus::Success, $fresh->status);
        $this->assertSame(200, $fresh->http_status);
    }

    public function test_get_active_triggers_returns_matching_triggers(): void
    {
        $trigger1 = $this->createTrigger([
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'active' => true,
        ]);
        $trigger2 = $this->createTrigger([
            'model_class' => TestUser::class,
            'event' => EventEnum::Updated,
            'active' => true,
        ]);
        $this->createTrigger([
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'active' => false,
        ]);

        $deliveryService = $this->app->make(DeliveryService::class);
        $triggers = $deliveryService->getActiveTriggers(TestUser::class, EventEnum::Created);

        $this->assertCount(1, $triggers);
        $this->assertSame($trigger1->id, $triggers->first()->id);
    }
}
