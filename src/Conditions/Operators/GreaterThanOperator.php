<?php

namespace Ashrafic\FilamentAutomationBridge\Conditions\Operators;

use Ashrafic\FilamentAutomationBridge\Contracts\ConditionOperator;

class GreaterThanOperator implements ConditionOperator
{
    public function evaluate(mixed $actual, mixed $expected, array $context = []): bool
    {
        return (float) $actual > (float) $expected;
    }

    public function key(): string
    {
        return 'greater_than';
    }

    public function label(): string
    {
        return 'Greater Than';
    }

    public function requiresValue(): bool
    {
        return true;
    }
}
