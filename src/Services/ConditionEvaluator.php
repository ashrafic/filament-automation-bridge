<?php

namespace Ashrafic\FilamentWebhookBridge\Services;

use Ashrafic\FilamentWebhookBridge\Conditions\ConditionRegistry;
use Ashrafic\FilamentWebhookBridge\Contracts\ConditionOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ConditionEvaluator
{
    public function __construct(protected ConditionRegistry $registry) {}

    public function evaluate(Model $model, ?array $conditions, array $original = []): bool
    {
        if ($conditions === null || $conditions === []) {
            return true;
        }

        $orGroups = $this->splitIntoOrGroups($conditions);

        foreach ($orGroups as $andGroup) {
            if ($this->evaluateAndGroup($model, $andGroup, $original)) {
                return true;
            }
        }

        return false;
    }

    public function evaluateSingle(Model $model, array $condition, array $original = []): bool
    {
        $field = $condition['field'] ?? null;
        $operatorKey = $condition['operator'] ?? null;
        $expected = $condition['value'] ?? null;

        if ($field === null || $operatorKey === null) {
            return false;
        }

        $actual = $this->getModelValue($model, $field);

        if (! $this->fieldExists($model, $field)) {
            Log::warning('ConditionEvaluator: field path does not exist on model', [
                'model' => get_class($model),
                'field' => $field,
            ]);

            return false;
        }

        try {
            $operator = $this->registry->get($operatorKey);
        } catch (\InvalidArgumentException $e) {
            Log::warning('ConditionEvaluator: unknown operator', [
                'operator' => $operatorKey,
            ]);

            return false;
        }

        $context = [];

        if (in_array($operatorKey, ['changed', 'changed_to'])) {
            $context['original'] = $original[$field] ?? null;
        }

        $actual = $this->normalizeValue($actual, $expected, $operatorKey);

        return $operator->evaluate($actual, $expected, $context);
    }

    public function registerOperator(string $name, ConditionOperator $operator): void
    {
        $this->registry->register($operator);
    }

    public function getModelValue(Model $model, string $fieldPath): mixed
    {
        return data_get($model, $fieldPath);
    }

    protected function splitIntoOrGroups(array $conditions): array
    {
        $groups = [];
        $currentGroup = [];

        foreach ($conditions as $condition) {
            $logic = $condition['logic'] ?? 'AND';

            if ($logic === 'OR' && $currentGroup !== []) {
                $groups[] = $currentGroup;
                $currentGroup = [];
            }

            $currentGroup[] = $condition;
        }

        if ($currentGroup !== []) {
            $groups[] = $currentGroup;
        }

        return $groups;
    }

    protected function evaluateAndGroup(Model $model, array $conditions, array $original): bool
    {
        foreach ($conditions as $condition) {
            if (! $this->evaluateSingle($model, $condition, $original)) {
                return false;
            }
        }

        return true;
    }

    protected function fieldExists(Model $model, string $fieldPath): bool
    {
        $segments = explode('.', $fieldPath);
        $current = $model;

        foreach ($segments as $i => $segment) {
            if ($current instanceof Model) {
                if (array_key_exists($segment, $current->getAttributes())) {
                    $current = $current->getAttribute($segment);

                    continue;
                }

                if (method_exists($current, $segment)) {
                    $current = $current->getRelationValue($segment);

                    continue;
                }

                $accessor = 'get'.ucfirst($segment).'Attribute';

                if (method_exists($current, $accessor)) {
                    $current = $current->$accessor();

                    continue;
                }

                return $i === array_key_last($segments);
            }

            if ($current === null) {
                return false;
            }

            if (is_array($current)) {
                if (! array_key_exists($segment, $current)) {
                    return false;
                }

                $current = $current[$segment];

                continue;
            }

            if (is_object($current)) {
                if (! property_exists($current, $segment) && ! method_exists($current, $segment)) {
                    return false;
                }

                $current = $current->$segment;

                continue;
            }

            return false;
        }

        return true;
    }

    protected function normalizeValue(mixed $actual, mixed &$expected, string $operatorKey): mixed
    {
        if ($actual === null) {
            return null;
        }

        if (is_bool($actual) && is_string($expected)) {
            $expected = match (strtolower($expected)) {
                'true' => true,
                'false' => false,
                default => $expected,
            };
        }

        return $actual;
    }
}
