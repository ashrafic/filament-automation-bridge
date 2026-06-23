<?php

namespace Ashrafic\FilamentAutomationBridge\Tests\Unit\Services;

use Ashrafic\FilamentAutomationBridge\Enums\DeliverySource;
use Ashrafic\FilamentAutomationBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Exceptions\SecurityException;
use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\SecurityService;
use Ashrafic\FilamentAutomationBridge\Tests\TestCase;

class SecurityServiceTest extends TestCase
{
    protected SecurityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(SecurityService::class);
    }

    public function test_signs_payload_with_hmac(): void
    {
        $payload = ['event' => 'created', 'data' => ['id' => 1]];
        $secret = 'test-secret-key';

        $headers = $this->service->sign($payload, $secret);

        $this->assertArrayHasKey('X-Automation-Signature', $headers);
        $this->assertArrayHasKey('X-Automation-Timestamp', $headers);
        $this->assertStringStartsWith('sha256=', $headers['X-Automation-Signature']);
    }

    public function test_sign_returns_empty_array_when_secret_is_null(): void
    {
        $payload = ['event' => 'created'];

        $headers = $this->service->sign($payload, null);

        $this->assertEmpty($headers);
    }

    public function test_sign_produces_deterministic_signature(): void
    {
        $payload = ['event' => 'created', 'data' => ['name' => 'test']];
        $secret = 'deterministic-secret';

        $headers1 = $this->service->sign($payload, $secret);
        $timestamp = $headers1['X-Automation-Timestamp'];

        $expectedSig = 'sha256='.hash_hmac('sha256', $timestamp.'.'.json_encode($payload), $secret);
        $this->assertSame($expectedSig, $headers1['X-Automation-Signature']);
    }

    public function test_validate_url_accepts_valid_https_url(): void
    {
        $this->service->validateUrl('https://example.com/webhook');

        $this->assertTrue(true);
    }

    public function test_validate_url_rejects_empty_url(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('URL cannot be empty');

        $this->service->validateUrl('');
    }

    public function test_validate_url_rejects_invalid_format(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid URL format');

        $this->service->validateUrl('not-a-url');
    }

    public function test_validate_url_rejects_blockeprivate_ips(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('blocked IP');

        $this->service->validateUrl('http://127.0.0.1/webhook');
    }

    public function test_validate_url_rejects_localhost(): void
    {
        $this->expectException(SecurityException::class);

        $this->service->validateUrl('http://localhost/webhook');
    }

    public function test_is_blocked_ip_detects_loopback(): void
    {
        $this->assertTrue($this->service->isBlockedIp('http://127.0.0.1/webhook'));
        $this->assertTrue($this->service->isBlockedIp('http://localhost/webhook'));
    }

    public function test_generate_secret_returns_64_char_hex(): void
    {
        $secret = $this->service->generateSecret();

        $this->assertSame(64, strlen($secret));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $secret);
    }

    public function test_generate_secret_produces_unique_values(): void
    {
        $secret1 = $this->service->generateSecret();
        $secret2 = $this->service->generateSecret();

        $this->assertNotSame($secret1, $secret2);
    }

    public function test_encrypt_payload_fields(): void
    {
        $payload = ['email' => 'test@example.com', 'name' => 'Test'];
        $result = $this->service->encryptPayloadFields($payload, ['email']);

        $this->assertNotSame('test@example.com', $result['email']);
        $this->assertSame('Test', $result['name']);
        $this->assertSame('test@example.com', decrypt($result['email']));
    }

    public function test_build_headers_includes_signatures(): void
    {
        $trigger = AutomationTrigger::create([
            'name' => 'Test',
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Custom,
            'destination_url' => 'https://example.com/hook',
            'field_mapping' => ['name'],
            'payload_mode' => PayloadMode::Summary,
            'secret' => 'my-secret',
            'active' => true,
            'max_retries' => 3,
            'request_timeout' => 5,
        ]);

        $delivery = AutomationDelivery::create([
            'trigger_id' => $trigger->id,
            'model_type' => 'App\\Models\\User',
            'model_id' => 1,
            'payload' => ['event' => 'created'],
            'status' => DeliveryStatus::Pending,
            'retry_count' => 0,
            'max_retries' => 3,
            'source' => DeliverySource::Realtime,
        ]);

        $headers = $this->service->buildHeaders($trigger, $delivery);

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('X-Automation-Signature', $headers);
        $this->assertArrayHasKey('X-Automation-Timestamp', $headers);
        $this->assertArrayHasKey('X-Automation-Trigger-Id', $headers);
        $this->assertArrayHasKey('X-Automation-Delivery-Id', $headers);
        $this->assertSame('application/json', $headers['Content-Type']);
    }
}
