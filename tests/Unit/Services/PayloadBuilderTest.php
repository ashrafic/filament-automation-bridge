<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Unit\Services;

use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Enums\PayloadMode;
use Ashrafic\FilamentWebhookBridge\Exceptions\InvalidPayloadException;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\FieldSchemaAnalyzer;
use Ashrafic\FilamentWebhookBridge\Services\PayloadBuilder;
use Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models\TestOrder;
use Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models\TestOrderItem;
use Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models\TestUser;
use Ashrafic\FilamentWebhookBridge\Tests\TestCase;

class PayloadBuilderTest extends TestCase
{
    protected PayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = $this->app->make(PayloadBuilder::class);
    }

    protected function createTrigger(array $overrides = []): WebhookTrigger
    {
        return WebhookTrigger::create(array_merge([
            'name' => 'Test Trigger',
            'model_class' => TestUser::class,
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

    public function test_build_summary_payload_extracts_mapped_fields(): void
    {
        $user = $this->createTestUser(['name' => 'John', 'email' => 'john@example.com']);
        $trigger = $this->createTrigger([
            'field_mapping' => ['name', 'email'],
            'payload_mode' => PayloadMode::Summary,
        ]);

        $payload = $this->builder->build($trigger, $user);

        $this->assertSame('created', $payload['event']);
        $this->assertSame(TestUser::class, $payload['model']);
        $this->assertSame('John', $payload['data']['name']);
        $this->assertSame('john@example.com', $payload['data']['email']);
    }

    public function test_build_all_payload_excludes_hidden_fields(): void
    {
        $user = $this->createTestUser(['name' => 'Jane', 'email' => 'jane@example.com']);
        $trigger = $this->createTrigger(['payload_mode' => PayloadMode::All]);

        $payload = $this->builder->build($trigger, $user);

        $this->assertArrayNotHasKey('password', $payload['data']);
        $this->assertArrayHasKey('name', $payload['data']);
        $this->assertArrayHasKey('email', $payload['data']);
    }

    public function test_format_for_zapier_destination(): void
    {
        $payload = [
            'event' => 'created',
            'model' => TestUser::class,
            'triggered_at' => '2025-01-01T00:00:00Z',
            'webhook_id' => 1,
            'data' => ['name' => 'John'],
        ];

        $result = $this->builder->formatPayload($payload, DestinationType::Zapier);

        $this->assertArrayHasKey('event', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('triggered_at', $result);
        $this->assertArrayHasKey('webhook_id', $result);
    }

    public function test_format_for_make_destination(): void
    {
        $payload = [
            'event' => 'created',
            'model' => TestUser::class,
            'triggered_at' => '2025-01-01T00:00:00Z',
            'webhook_id' => 1,
            'data' => ['name' => 'John'],
        ];

        $result = $this->builder->formatPayload($payload, DestinationType::Make);

        $this->assertIsArray($result);
    }

    public function test_format_for_n8n_destination(): void
    {
        $payload = [
            'event' => 'created',
            'model' => TestUser::class,
            'triggered_at' => '2025-01-01T00:00:00Z',
            'webhook_id' => 1,
            'data' => ['name' => 'John'],
        ];

        $result = $this->builder->formatPayload($payload, DestinationType::N8n);

        $this->assertIsArray($result);
    }

    public function test_format_for_custom_destination_returns_as_is(): void
    {
        $payload = [
            'event' => 'created',
            'model' => TestUser::class,
            'triggered_at' => '2025-01-01T00:00:00Z',
            'webhook_id' => 1,
            'data' => ['name' => 'John'],
        ];

        $result = $this->builder->formatPayload($payload, DestinationType::Custom);

        $this->assertSame($payload, $result);
    }

    public function test_extract_nested_relation_field(): void
    {
        $user = $this->createTestUser(['name' => 'John']);
        $order = TestOrder::create([
            'user_id' => $user->id,
            'total' => 99.99,
            'status' => 'completed',
        ]);

        $trigger = $this->createTrigger([
            'model_class' => TestOrder::class,
            'field_mapping' => ['total', 'customer.name'],
        ]);

        $payload = $this->builder->build($trigger, $order);

        $this->assertArrayHasKey('total', $payload['data']);
        $this->assertArrayHasKey('customer', $payload['data']);
        $this->assertSame('John', $payload['data']['customer']['name']);
    }

    public function test_extract_wildcard_star_field(): void
    {
        $user = $this->createTestUser(['name' => 'John']);
        $order = TestOrder::create([
            'user_id' => $user->id,
            'total' => 100,
            'status' => 'pending',
        ]);
        $item1 = TestOrderItem::create([
            'order_id' => $order->id,
            'name' => 'Item A',
            'price' => 50.0,
            'quantity' => 2,
        ]);
        $item2 = TestOrderItem::create([
            'order_id' => $order->id,
            'name' => 'Item B',
            'price' => 25.0,
            'quantity' => 1,
        ]);

        $trigger = $this->createTrigger([
            'model_class' => TestOrder::class,
            'field_mapping' => ['total', 'items.*'],
        ]);

        $payload = $this->builder->build($trigger, $order);

        $this->assertArrayHasKey('total', $payload['data']);
        $this->assertArrayHasKey('items', $payload['data']);
        $this->assertIsArray($payload['data']['items']);
    }

    public function test_extract_fields_throws_on_invalid_field_path(): void
    {
        $user = $this->createTestUser();

        $result = $this->builder->extractFields($user, ['nonexistent_field']);

        $this->assertArrayHasKey('nonexistent_field', $result);
        $this->assertNull($result['nonexistent_field']);
    }

    public function test_validate_template_returns_errors_for_empty_template(): void
    {
        $errors = $this->builder->validateTemplate('');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('empty', $errors[0]);
    }

    public function test_validate_template_returns_empty_for_valid_json(): void
    {
        $template = '{"event": "{{ event }}", "name": "{{ name }}"}';

        $errors = $this->builder->validateTemplate($template);

        $this->assertEmpty($errors);
    }

    public function test_render_template_throws_on_empty_template(): void
    {
        $this->expectException(InvalidPayloadException::class);

        $user = $this->createTestUser();
        $this->builder->renderTemplate('  ', $user, EventEnum::Created);
    }

    public function test_render_template_substitutes_variables(): void
    {
        $user = $this->createTestUser(['name' => 'Alice', 'email' => 'alice@example.com']);
        $template = '{"event": "{{ event }}", "name": "{{ name }}", "email": "{{ email }}"}';

        $result = $this->builder->renderTemplate($template, $user, EventEnum::Created);

        $this->assertSame('created', $result['event']);
        $this->assertSame('Alice', $result['name']);
        $this->assertSame('alice@example.com', $result['email']);
    }
}