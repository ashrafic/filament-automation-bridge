<?php

namespace Ashrafic\FilamentWebhookBridge\Contracts;

interface ConditionOperator
{
    public function evaluate(mixed $actual, mixed $expected, array $context = []): bool;

    public function key(): string;

    public function label(): string;

    public function requiresValue(): bool;
}