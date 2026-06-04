<?php

namespace Ashrafic\FilamentWebhookBridge\Conditions;

use Ashrafic\FilamentWebhookBridge\Conditions\Operators\ChangedOperator;
use Ashrafic\FilamentWebhookBridge\Conditions\Operators\ChangedToOperator;
use Ashrafic\FilamentWebhookBridge\Conditions\Operators\ContainsOperator;
use Ashrafic\FilamentWebhookBridge\Conditions\Operators\EqualsOperator;
use Ashrafic\FilamentWebhookBridge\Conditions\Operators\GreaterThanOperator;
use Ashrafic\FilamentWebhookBridge\Conditions\Operators\IsEmptyOperator;
use Ashrafic\FilamentWebhookBridge\Conditions\Operators\IsNotEmptyOperator;
use Ashrafic\FilamentWebhookBridge\Conditions\Operators\LessThanOperator;
use Ashrafic\FilamentWebhookBridge\Conditions\Operators\NotEqualsOperator;
use Ashrafic\FilamentWebhookBridge\Contracts\ConditionOperator;

class ConditionRegistry
{
    /** @var array<string, ConditionOperator> */
    private array $operators = [];

    public function __construct()
    {
        $this->register(new EqualsOperator);
        $this->register(new NotEqualsOperator);
        $this->register(new ContainsOperator);
        $this->register(new GreaterThanOperator);
        $this->register(new LessThanOperator);
        $this->register(new IsEmptyOperator);
        $this->register(new IsNotEmptyOperator);
        $this->register(new ChangedOperator);
        $this->register(new ChangedToOperator);
    }

    public function register(ConditionOperator $operator): void
    {
        $this->operators[$operator->key()] = $operator;
    }

    public function get(string $key): ConditionOperator
    {
        if (! isset($this->operators[$key])) {
            throw new \InvalidArgumentException("Condition operator [{$key}] not found.");
        }

        return $this->operators[$key];
    }

    /** @return array<string, ConditionOperator> */
    public function all(): array
    {
        return $this->operators;
    }

    /** @return array<string, ConditionOperator> */
    public function forCreatedEvent(): array
    {
        return array_filter($this->operators, fn (ConditionOperator $op) => ! in_array($op->key(), ['changed', 'changed_to']));
    }

    /** @return array<string, ConditionOperator> */
    public function forUpdatedEvent(): array
    {
        return $this->operators;
    }
}
