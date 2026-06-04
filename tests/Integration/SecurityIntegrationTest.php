<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Integration;

use Ashrafic\FilamentWebhookBridge\Enums\DeliverySource;
use Ashrafic\FilamentWebhookBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Enums\PayloadMode;
use Ashrafic\FilamentWebhookBridge\Exceptions\SecurityException;
use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\SecurityService;
use Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models\TestUser;
use Ashrafic\FilamentWebhookBridge\Tests\TestCase;

class SecurityIntegrationTest extends TestCase
{
    protected SecurityService $securityService;

    protected function createTrigger(array $overrides = []): WebhookTrigger
    {
        return WebhookTrigger::create(array_merge([
            'name' => 'Security Test Trigger',
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->securityService = $this->app->make(SecurityService::class);
    }

    public function test_it_blocks_ssrf_to_localhost(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('blocked IP');

        $this->securityService->validateUrl('http://localhost/webhook');
    }

    public function test_it_blocks_ssrf_to_private_ip(): void
    {
        $privateUrls = [
            'http://127.0.0.1/webhook',
            'http://10.0.0.1/webhook',
            'http://172.16.0.1/webhook',
            'http://192.168.1.1/webhook',
        ];

        foreach ($privateUrls as $url) {
            $this->assertTrue(
                $this->securityService->isBlockedIp($url),
                "Expected URL to be blocked: {$url}"
            );
        }
    }

    public function test_it_allows_valid_public_url(): void
    {
        $this->securityService->validateUrl('https://example.com/webhook');

        $this->assertFalse(
            $this->securityService->isBlockedIp('https://example.com/webhook'),
            'Expected public URL to be allowed'
        );
    }

    public function test_it_encrypts_secret_at_rest(): void
    {
        $rawSecret = 'my-super-secret-key';

        $trigger = $this->createTrigger([
            'secret' => $rawSecret,
        ]);

        $dbSecret = $trigger->getRawOriginal('secret');
        $this->assertNotSame($rawSecret, $dbSecret, 'Secret should be encrypted in the database');
        $this->assertNotEquals($rawSecret, $dbSecret, 'Secret should be encrypted in the database');

        $trigger->refresh();
        $this->assertSame($rawSecret, $trigger->secret, 'Secret should decrypt correctly when accessed via attribute');
    }

    public function test_it_includes_timestamp_in_headers(): void
    {
        $trigger = $this->createTrigger([
            'secret' => 'test-secret',
        ]);

        $delivery = WebhookDelivery::create([
            'trigger_id' => $trigger->id,
            'model_type' => 'TestUser',
            'model_id' => 1,
            'payload' => ['event' => 'created'],
            'status' => DeliveryStatus::Pending,
            'retry_count' => 0,
            'max_retries' => 3,
            'source' => DeliverySource::Realtime,
        ]);

        $headers = $this->securityService->buildHeaders($trigger, $delivery);

        $this->assertArrayHasKey('X-Webhook-Timestamp', $headers);
        $this->assertArrayHasKey('X-Webhook-Signature', $headers);
        $this->assertMatchesRegularExpression('/^\d+$/', $headers['X-Webhook-Timestamp']);
        $this->assertStringStartsWith('sha256=', $headers['X-Webhook-Signature']);
    }
}
