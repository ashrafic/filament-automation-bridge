<?php

namespace Ashrafic\FilamentAutomationBridge\Services;

use Ashrafic\FilamentAutomationBridge\Exceptions\DeliveryFailedException;
use Illuminate\Support\Facades\RateLimiter;

class RateLimiterService
{
    public function throttle(string $destinationUrl): void
    {
        $hostname = $this->getHostname($destinationUrl);
        $key = "automation-bridge:{$hostname}";
        $maxRequestsPerMinute = config('filament-automation-bridge.rate_limiting.max_requests_per_minute', 60);

        if (RateLimiter::tooManyAttempts($key, $maxRequestsPerMinute)) {
            throw new DeliveryFailedException("Rate limit exceeded for host: {$hostname}. Please retry later.");
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
