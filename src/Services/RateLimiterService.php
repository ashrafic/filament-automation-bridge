<?php

namespace Ashrafic\FilamentAutomationBridge\Services;

use Ashrafic\FilamentAutomationBridge\Events\RateLimitHit;
use Ashrafic\FilamentAutomationBridge\Exceptions\RateLimitException;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Illuminate\Support\Facades\RateLimiter;

class RateLimiterService
{
    public function throttle(string $destinationUrl, ?AutomationTrigger $trigger = null): void
    {
        $hostname = $this->getHostname($destinationUrl);
        $key = "automation-bridge:{$hostname}";
        $maxRequestsPerMinute = config('filament-automation-bridge.rate_limiting.max_requests_per_minute', 60);

        if (RateLimiter::tooManyAttempts($key, $maxRequestsPerMinute)) {
            event(new RateLimitHit($hostname, $trigger));

            throw new RateLimitException($hostname, $maxRequestsPerMinute);
        }

        RateLimiter::hit($key);
    }

    public function isLimited(string $destinationUrl): bool
    {
        $hostname = $this->getHostname($destinationUrl);
        $key = "automation-bridge:{$hostname}";
        $maxRequestsPerMinute = config('filament-automation-bridge.rate_limiting.max_requests_per_minute', 60);

        return RateLimiter::tooManyAttempts($key, $maxRequestsPerMinute);
    }

    public function getHostname(string $url): string
    {
        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['host'])) {
            return $url;
        }

        return $parsed['host'];
    }

    public function clear(string $hostname): void
    {
        $key = "automation-bridge:{$hostname}";

        RateLimiter::clear($key);
    }
}
