<?php

namespace Ashrafic\FilamentWebhookBridge\Conditions\Operators;

use Ashrafic\FilamentWebhookBridge\Contracts\ConditionOperator;
use Illuminate\Support\Collection;

class IsEmptyOperator implements ConditionOperator
{
    public function evaluate(mixed $actual, mixed $expected, array $context = []): bool
    {
        if ($actual === null) {
            return true;
        }

        if ($actual === '') {
            return true;
        }

        if (is_array($actual) && $actual === []) {
            return true;
        }

        if ($actual instanceof Collection && $actual->isEmpty()) {
            return true;
        }

        return false;
    }

    public function key(): string
    {
        return 'is_empty';
    }

    public function label(): string
    {
        return 'Is Empty';
    }

    public function requiresValue(): bool
    {
        return false;
    }
}
