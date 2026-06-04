<?php

namespace Ashrafic\FilamentWebhookBridge\Conditions\Operators;

use Ashrafic\FilamentWebhookBridge\Contracts\ConditionOperator;
use Illuminate\Support\Collection;

class ContainsOperator implements ConditionOperator
{
    public function evaluate(mixed $actual, mixed $expected, array $context = []): bool
    {
        if ($actual instanceof Collection) {
            return $actual->contains($expected);
        }

        if (is_array($actual)) {
            return in_array($expected, $actual);
        }

        if (is_string($actual)) {
            return str_contains($actual, (string) $expected);
        }

        return false;
    }

    public function key(): string
    {
        return 'contains';
    }

    public function label(): string
    {
        return 'Contains';
    }

    public function requiresValue(): bool
    {
        return true;
    }
}
