<?php

namespace Ashrafic\FilamentAutomationBridge\Exceptions;

class RateLimitException extends \RuntimeException
{
    public function __construct(
        string $hostname,
        int $maxRequestsPerMinute,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Rate limit exceeded for host '{$hostname}' (max {$maxRequestsPerMinute} requests/min). Please retry later.",
            $code,
            $previous,
        );
    }
}
