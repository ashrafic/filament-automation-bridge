<?php

namespace Ashrafic\FilamentWebhookBridge\Conditions\Operators;

use Ashrafic\FilamentWebhookBridge\Contracts\ConditionOperator;
use Illuminate\Support\Collection;

class IsNotEmptyOperator implements ConditionOperator
{
    public function evaluate(mixed $actual, mixed $expected, array $context = []): bool
    {
        if ($actual === null) {
            return false;
        }

        if ($actual === '') {
            return false;
        }

        if (is_array($actual) && $actual === []) {
            return false;
        }

        if ($actual instanceof Collection && $actual->isEmpty()) {
            return false;
        }

        return true;
    }

    public function key(): string
    {
        return 'is_not_empty';
    }

    public function label(): string
    {
        return 'Is Not Empty';
    }

    public function requiresValue(): bool
    {
        return false;
    }
}
