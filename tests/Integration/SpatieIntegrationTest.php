<?php

namespace Ashrafic\FilamentAutomationBridge\Tests\Integration;

use Ashrafic\FilamentAutomationBridge\Enums\DeliverySource;
use Ashrafic\FilamentAutomationBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Ashrafic\FilamentAutomationBridge\Tests\Fixtures\Models\TestUser;
use Ashrafic\FilamentAutomationBridge\Tests\TestCase;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;

class SpatieIntegrationTest extends TestCase
{
    protected function createTrigger(array $overrides = []): AutomationTrigger
    {
        return AutomationTrigger::create(array_merge([
            'name' => 'Spatie Test Trigger',
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Custom,
            'destination_url' => 'https://httpbin.org/post',
            'field_mapping' => ['name', 'email'],
            'payload_mode' => PayloadMode::Summary,
            'active' => true,
            'max_retries' => 3,
            'request_timeout' => 5,
        ], $overrides));
    }

    public function test_it_creates_automation_call_with_correct_config(): void
    {
        Config::set('filament-automation-bridge.sandbox_mode', true);

        $trigger = $this->createTrigger([
            'destination_url' => 'https://example.com/webhook',
            'secret' => 'my-secret-key',
        ]);

        $user = TestUser::create(['name' => 'Spatie User', 'email' => 'spatie@example.com']);

        $deliveryService = $this->app->make(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $user, EventEnum::Created);

        $this->assertNotNull($delivery);
        $this->assertInstanceOf(AutomationDelivery::class, $delivery);
        $this->assertSame($trigger->id, $delivery->trigger_id);
        $this->assertSame(TestUser::class, $delivery->model_type);
        $this->assertSame($user->id, $delivery->model_id);
        $this->assertNotNull($delivery->payload);
        $this->assertNotNull($delivery->headers);
        $this->assertSame(DeliverySource::Realtime, $delivery->source);

        $fresh = $delivery->fresh();
        $this->assertSame(DeliveryStatus::Success, $fresh->status);
    }

    public function test_it_uses_dedicated_queue(): void
    {
        Config::set('filament-automation-bridge.sandbox_mode', true);
        Config::set('filament-automation-bridge.queue.queue_name', 'webhooks');

        $trigger = $this->createTrigger();
        $user = TestUser::create(['name' => 'Queue Test', 'email' => 'queue@example.com']);

        $deliveryService = $this->app->make(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $user, EventEnum::Created);

        $this->assertNotNull($delivery);
        $this->assertSame(DeliveryStatus::Success, $delivery->status);
    }

    public function test_it_listens_for_spatie_success_event(): void
    {
        $trigger = $this->createTrigger([
            'destination_url' => 'https://example.com/webhook',
        ]);

        $delivery = AutomationDelivery::create([
            'trigger_id' => $trigger->id,
            'model_type' => TestUser::class,
            'model_id' => 1,
            'payload' => ['event' => 'created'],
            'status' => DeliveryStatus::Pending,
            'retry_count' => 0,
            'max_retries' => 3,
            'source' => DeliverySource::Realtime,
        ]);

        $response = new Response(200, ['X-Test' => 'ok'], '{"success":true}');

        $event = new WebhookCallSucceededEvent(
            httpVerb: 'POST',
            webhookUrl: 'https://example.com/webhook',
            payload: ['event' => 'created'],
            headers: [],
            meta: [],
            tags: ['automation-bridge', 'delivery:'.$delivery->uuid],
            attempt: 1,
            response: $response,
            errorType: null,
            errorMessage: null,
            uuid: $delivery->uuid,
            transferStats: null,
        );

        Event::dispatch($event);

        $fresh = $delivery->fresh();
        $this->assertSame(DeliveryStatus::Success, $fresh->status);
        $this->assertSame(200, $fresh->http_status);
    }

    public function test_it_listens_for_spatie_failure_event(): void
    {
        $trigger = $this->createTrigger([
            'destination_url' => 'https://example.com/webhook',
        ]);

        $delivery = AutomationDelivery::create([
            'trigger_id' => $trigger->id,
            'model_type' => TestUser::class,
            'model_id' => 1,
            'payload' => ['event' => 'created'],
            'status' => DeliveryStatus::Pending,
            'retry_count' => 0,
            'max_retries' => 3,
            'source' => DeliverySource::Realtime,
        ]);

        $event = new WebhookCallFailedEvent(
            httpVerb: 'POST',
            webhookUrl: 'https://example.com/webhook',
            payload: ['event' => 'created'],
            headers: [],
            meta: [],
            tags: ['automation-bridge', 'delivery:'.$delivery->uuid],
            attempt: 1,
            response: null,
            errorType: 'ConnectionError',
            errorMessage: 'Connection refused',
            uuid: $delivery->uuid,
            transferStats: null,
        );

        Event::dispatch($event);

        $fresh = $delivery->fresh();
        $this->assertSame(DeliveryStatus::Failed, $fresh->status);
        $this->assertStringContainsString('Connection refused', $fresh->error_message);
    }
}
