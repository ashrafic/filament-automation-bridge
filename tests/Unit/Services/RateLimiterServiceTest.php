<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Unit\Services;

use Ashrafic\FilamentWebhookBridge\Exceptions\DeliveryFailedException;
use Ashrafic\FilamentWebhookBridge\Services\RateLimiterService;
use Ashrafic\FilamentWebhookBridge\Tests\TestCase;

class RateLimiterServiceTest extends TestCase
{
    protected RateLimiterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(RateLimiterService::class);
    }

    public function test_allows_requests_within_limit(): void
    {
        $this->expectNotToPerformAssertions();

        $this->service->throttle('https://example.com/webhook');
    }

    public function test_blocks_requests_exceeding_limit(): void
    {
        config(['filament-webhook-bridge.rate_limiting.max_requests_per_minute' => 2]);

        $this->service->throttle('https://example.com/webhook');
        $this->service->throttle('https://example.com/webhook');

        $this->expectException(DeliveryFailedException::class);
        $this->service->throttle('https://example.com/webhook');
    }

    public function test_is_limited_returns_false_within_limit(): void
    {
        $this->assertFalse($this->service->isLimited('https://example.com/webhook'));
    }

    public function test_is_limited_returns_true_after_exhausting_limit(): void
    {
        config(['filament-webhook-bridge.rate_limiting.max_requests_per_minute' => 1]);

        $this->service->throttle('https://example.com/webhook');

        $this->assertTrue($this->service->isLimited('https://example.com/webhook'));
    }

    public function test_extracts_hostname_from_url(): void
    {
        $hostname = $this->service->getHostname('https://example.com/webhook');

        $this->assertSame('example.com', $hostname);
    }

    public function test_extracts_hostname_from_url_without_path(): void
    {
        $hostname = $this->service->getHostname('https://example.com');

        $this->assertSame('example.com', $hostname);
    }

    public function test_get_hostname_returns_url_when_parsing_fails(): void
    {
        $hostname = $this->service->getHostname('not-a-url');

        $this->assertSame('not-a-url', $hostname);
    }

    public function test_clears_rate_limiter_for_hostname(): void
    {
        config(['filament-webhook-bridge.rate_limiting.max_requests_per_minute' => 1]);

        $this->service->throttle('https://example.com/webhook');
        $this->assertTrue($this->service->isLimited('https://example.com/webhook'));

        $this->service->clear('example.com');
        $this->assertFalse($this->service->isLimited('https://example.com/webhook'));
    }

    public function test_different_hosts_tracked_separately(): void
    {
        config(['filament-webhook-bridge.rate_limiting.max_requests_per_minute' => 1]);

        $this->service->throttle('https://example.com/webhook');
        $this->assertTrue($this->service->isLimited('https://example.com/webhook'));

        $this->assertFalse($this->service->isLimited('https://other.com/webhook'));
    }
}
