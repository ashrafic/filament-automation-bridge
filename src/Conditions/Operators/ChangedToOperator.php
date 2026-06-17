<?php

namespace Ashrafic\FilamentAutomationBridge\Conditions\Operators;

use Ashrafic\FilamentAutomationBridge\Contracts\ConditionOperator;

class ChangedToOperator implements ConditionOperator
{
    public function evaluate(mixed $actual, mixed $expected, array $context = []): bool
    {
        if (! isset($context['original'])) {
            if (is_numeric($actual) && is_numeric($expected)) {
                return (float) $actual === (float) $expected;
            }

            return $actual === $expected;
        }

        if ($context['original'] === $actual) {
            return false;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return $actual === $expected;
    }

    public function key(): string
    {
        return 'changed_to';
    }

    public function label(): string
    {
        return 'Changed To';
    }

    public function requiresValue(): bool
    {
        return true;
    }
}
