<?php

namespace Ashrafic\FilamentWebhookBridge\Conditions\Operators;

use Ashrafic\FilamentWebhookBridge\Contracts\ConditionOperator;

class ChangedOperator implements ConditionOperator
{
    public function evaluate(mixed $actual, mixed $expected, array $context = []): bool
    {
        if (! isset($context['original'])) {
            return true;
        }

        return $context['original'] !== $actual;
    }

    public function key(): string
    {
        return 'changed';
    }

    public function label(): string
    {
        return 'Changed';
    }

    public function requiresValue(): bool
    {
        return false;
    }
}
