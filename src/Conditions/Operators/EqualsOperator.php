<?php

namespace Ashrafic\FilamentWebhookBridge\Conditions\Operators;

use Ashrafic\FilamentWebhookBridge\Contracts\ConditionOperator;

class EqualsOperator implements ConditionOperator
{
    public function evaluate(mixed $actual, mixed $expected, array $context = []): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return $actual === $expected;
    }

    public function key(): string
    {
        return 'equals';
    }

    public function label(): string
    {
        return 'Equals';
    }

    public function requiresValue(): bool
    {
        return true;
    }
}
