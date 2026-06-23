<?php

namespace Ashrafic\FilamentAutomationBridge\Conditions\Operators;

use Ashrafic\FilamentAutomationBridge\Contracts\ConditionOperator;

class LessThanOperator implements ConditionOperator
{
    public function evaluate(mixed $actual, mixed $expected, array $context = []): bool
    {
        return (float) $actual < (float) $expected;
    }

    public function key(): string
    {
        return 'less_than';
    }

    public function label(): string
    {
        return 'Less Than';
    }

    public function requiresValue(): bool
    {
        return true;
    }
}
