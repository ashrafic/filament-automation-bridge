<?php

namespace Ashrafic\FilamentAutomationBridge\Services;

use Ashrafic\FilamentAutomationBridge\Contracts\PayloadFormatter;
use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Exceptions\InvalidPayloadException;
use Ashrafic\FilamentAutomationBridge\Exceptions\ModelNotFoundException;
use Ashrafic\FilamentAutomationBridge\Formatters\CustomFormatter;
use Ashrafic\FilamentAutomationBridge\Formatters\MakeFormatter;
use Ashrafic\FilamentAutomationBridge\Formatters\N8nFormatter;
use Ashrafic\FilamentAutomationBridge\Formatters\ZapierFormatter;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayloadBuilder
{
    public function __construct(
        private FieldSchemaAnalyzer $schemaAnalyzer,
    ) {}

    public function build(AutomationTrigger $trigger, Model $model): array
    {
        $payloadMode = $trigger->payload_mode;

        $data = match ($payloadMode) {
            PayloadMode::Summary => $this->extractFields($model, $trigger->field_mapping ?? []) ?: $this->extractAllAttributes($model),
            PayloadMode::All => $this->extractAllAttributes($model),
            PayloadMode::Custom => $this->renderTemplate(
                $trigger->custom_payload_template ?? '',
                $model,
                $trigger->event,
            ),
        };

        $envelope = [
            'event' => $trigger->event->value,
            'model' => get_class($model),
            'triggered_at' => now()->toIso8601String(),
            'automation_id' => $trigger->id,
            'data' => $data,
        ];

        $this->validatePayloadSize($envelope, $trigger);

        return $envelope;
    }

    public function buildSample(AutomationTrigger $trigger): array
    {
        $modelClass = $trigger->model_class;

        if (! class_exists($modelClass)) {
            throw ModelNotFoundException::forClass($modelClass);
        }

        $model = new $modelClass;

        $sampleData = $this->buildSampleData($model, $trigger->field_mapping ?? []);

        return [
            'event' => $trigger->event->value,
            'model' => $modelClass,
            'triggered_at' => now()->toIso8601String(),
            'automation_id' => $trigger->id,
            'data' => $sampleData,
        ];
    }

    public function extractFields(Model $model, array $fields): array
    {
        if (empty($fields)) {
            return [];
        }

        $relations = $this->extractEagerLoadRelations($fields);

        if (! empty($relations)) {
            $model->loadMissing($relations);
        }

        $result = [];

        foreach ($fields as $fieldPath) {
            $fieldPath = (string) $fieldPath;

            if (Str::contains($fieldPath, '.*.')) {
                $result = $this->extractWildcardField($model, $fieldPath, $result);
            } elseif (Str::contains($fieldPath, '.*')) {
                $result = $this->extractWildcardStar($model, $fieldPath, $result);
            } elseif (Str::contains($fieldPath, '.')) {
                $value = data_get($model, $fieldPath);
                $result = $this->setNestedValue($result, $fieldPath, $value);
            } else {
                $value = $model->getAttribute($fieldPath);
                $result[$fieldPath] = $this->formatValue($fieldPath, $value, $model);
            }
        }

        return $result;
    }

    public function formatPayload(array $payload, DestinationType $destinationType): array
    {
        $formatter = $this->resolveFormatter($destinationType);

        if ($formatter === null) {
            return $payload;
        }

        $metadata = [
            'event' => $payload['event'] ?? '',
            'triggered_at' => $payload['triggered_at'] ?? '',
            'automation_id' => $payload['automation_id'] ?? '',
        ];

        return $formatter->format($payload, $metadata);
    }

    public function renderTemplate(string $template, Model $model, EventEnum $event): array
    {
        if (empty(trim($template))) {
            throw InvalidPayloadException::emptyTemplate();
        }

        $errors = $this->validateTemplate($template);

        if (! empty($errors)) {
            throw InvalidPayloadException::templateErrors($errors);
        }

        $replacements = [
            'event' => $event->value,
            'model' => get_class($model),
        ];

        $modelAttributes = $model->toArray();
        $allValues = array_merge($replacements, $modelAttributes);

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)(?:\s*\|\s*json)?\s*\}\}/',
            function ($match) use ($allValues) {
                $key = $match[1];
                $value = data_get($allValues, $key, $match[0]);

                if (is_array($value) || is_object($value)) {
                    return json_encode($value) ?: $match[0];
                }

                return (string) $value;
            },
            $template,
        );

        $decoded = json_decode($rendered, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidPayloadException::invalidJson(json_last_error_msg());
        }

        if (! is_array($decoded)) {
            throw InvalidPayloadException::invalidJson('Template must produce a JSON object');
        }

        return $decoded;
    }

    public function validateTemplate(string $template): array
    {
        $errors = [];

        if (empty(trim($template))) {
            $errors[] = 'Template is empty.';

            return $errors;
        }

        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)(?:\s*\|\s*json\s*)?\}\}/', $template, $matches);

        foreach ($matches[1] as $placeholder) {
            $trimmed = trim($placeholder);

            if (empty($trimmed)) {
                $errors[] = 'Empty placeholder {{ }} found.';
            }

            if (! preg_match('/^[a-zA-Z0-9_.]+$/', $trimmed)) {
                $errors[] = "Invalid placeholder name: '{{ {$trimmed} }}'. Only alphanumeric characters, dots, and underscores are allowed.";
            }
        }

        $openCount = substr_count($template, '{{');
        $closeCount = substr_count($template, '}}');

        if ($openCount !== $closeCount) {
            $errors[] = 'Unclosed placeholder detected. Each {{ must have a matching }}.';
        }

        $decoded = json_decode($template, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $placeholdersReplaced = preg_replace('/\{\{\s*[a-zA-Z0-9_.]+(?:\s*\|\s*json\s*)?\}\}/', '"placeholder"', $template);
            $decoded = json_decode($placeholdersReplaced, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Template must be valid JSON structure. '.json_last_error_msg();
            }
        }

        return $errors;
    }

    protected function extractAllAttributes(Model $model): array
    {
        $hidden = $model->getHidden();
        $excluded = config('filament-automation-bridge.field_schema.excluded_attributes', [
            'password',
            'remember_token',
            'api_token',
        ]);

        $attributes = $model->getAttributes();

        $result = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $hidden)) {
                continue;
            }

            if (in_array($key, $excluded)) {
                continue;
            }

            if ($this->isBinaryColumn($key, $model)) {
                continue;
            }

            $result[$key] = $this->formatValue($key, $value, $model);
        }

        return $result;
    }

    protected function extractEagerLoadRelations(array $fields): array
    {
        $relations = [];

        foreach ($fields as $fieldPath) {
            $fieldPath = (string) $fieldPath;

            if (! Str::contains($fieldPath, '.')) {
                continue;
            }

            $segments = explode('.', $fieldPath);
            $relationParts = [];

            for ($i = 0; $i < count($segments) - 1; $i++) {
                $segment = $segments[$i];

                if ($segment === '*') {
                    break;
                }

                $relationParts[] = $segment;
            }

            if (! empty($relationParts)) {
                $relationPath = implode('.', $relationParts);

                if (! in_array($relationPath, $relations)) {
                    $relations[] = $relationPath;
                }
            }
        }

        return $relations;
    }

    protected function extractWildcardField(Model $model, string $fieldPath, array $result): array
    {
        $parts = explode('.*.', $fieldPath, 2);
        $relationName = $parts[0];
        $nestedField = $parts[1] ?? null;

        $collection = $model->getRelation($relationName);

        if ($collection === null) {
            $collection = data_get($model, $relationName);
        }

        if (! $collection instanceof Collection) {
            $value = data_get($model, $fieldPath);

            return $this->setNestedValue($result, $fieldPath, $value);
        }

        $items = [];

        foreach ($collection as $item) {
            if ($nestedField) {
                if ($item instanceof Model) {
                    $value = $item->getAttribute($nestedField);
                    $items[] = $this->formatValue($nestedField, $value, $item);
                } else {
                    $items[] = data_get($item, $nestedField);
                }
            }
        }

        $key = $relationName;

        if (Str::contains($fieldPath, '.') && substr_count($fieldPath, '.') > 1) {
            $prefix = Str::before($fieldPath, '.'.$relationName.'.*');

            if (! empty($prefix)) {
                $key = $prefix.'.'.$relationName;

                $existing = data_get($result, $key.'.'.$nestedField);
                $resultNested = data_get($result, $key, []);

                if (! empty($resultNested)) {
                    $resultNested[$nestedField] = $items;
                    data_set($result, $key, $resultNested);

                    return $result;
                }

                data_set($result, $key.'.'.$nestedField, $items);

                return $result;
            }
        }

        if ($nestedField) {
            $result[$relationName] = [$nestedField => $items];
        } else {
            $result[$relationName] = $items;
        }

        return $result;
    }

    protected function extractWildcardStar(Model $model, string $fieldPath, array $result): array
    {
        $parts = explode('.*', $fieldPath, 2);
        $relationName = $parts[0];

        $collection = $model->getRelation($relationName);

        if ($collection === null) {
            $collection = data_get($model, $relationName);
        }

        if ($collection instanceof Collection) {
            $items = $collection->map(fn ($item) => $item instanceof Model ? $this->extractAllAttributes($item) : $item)->toArray();
        } elseif (is_array($collection)) {
            $items = $collection;
        } else {
            $items = data_get($model, $fieldPath);
        }

        return $this->setNestedValue($result, $relationName, $items);
    }

    protected function setNestedValue(array $result, string $path, mixed $value): array
    {
        $keys = explode('.', $path);
        $current = &$result;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (! isset($current[$key]) || ! is_array($current[$key])) {
                    $current[$key] = [];
                }

                $current = &$current[$key];
            }
        }

        return $result;
    }

    protected function formatValue(string $field, mixed $value, Model $model): mixed
    {
        if ($this->isDateTimeField($field, $model) && $value !== null) {
            if ($value instanceof Carbon) {
                return $value->toIso8601String();
            }

            if (is_string($value)) {
                try {
                    return Carbon::parse($value)->toIso8601String();
                } catch (\Throwable) {
                    return $value;
                }
            }
        }

        if ($this->isBinaryColumn($field, $model)) {
            return null;
        }

        return $value;
    }

    protected function isDateTimeField(string $field, Model $model): bool
    {
        $dates = $model->getDates();

        return in_array($field, $dates);
    }

    protected function isBinaryColumn(string $field, Model $model): bool
    {
        $columnType = $this->schemaAnalyzer->getAttributeColumnType(get_class($model), $field);

        if ($columnType === null) {
            return false;
        }

        $binaryTypes = ['blob', 'binary', 'varbinary', 'bytea', 'image', 'tinyblob', 'mediumblob', 'longblob'];

        return in_array(strtolower($columnType), $binaryTypes);
    }

    protected function validatePayloadSize(array $payload, AutomationTrigger $trigger): void
    {
        $maxSizeMb = config('filament-automation-bridge.security.max_payload_size_mb', 5);
        $maxSizeBytes = $maxSizeMb * 1024 * 1024;

        $encodedSize = strlen(json_encode($payload));

        if ($encodedSize > 1024 * 1024) {
            Log::warning('Automation payload exceeds 1MB', [
                'trigger_id' => $trigger->id,
                'size' => $encodedSize,
            ]);
        }

        if ($encodedSize > $maxSizeBytes) {
            throw InvalidPayloadException::payloadTooLarge($encodedSize, $maxSizeBytes);
        }
    }

    protected function buildSampleData(Model $model, array $fields): array
    {
        if (empty($fields)) {
            return $this->buildSampleFromAttributes($model);
        }

        $data = [];

        foreach ($fields as $fieldPath) {
            $fieldPath = (string) $fieldPath;

            if (Str::contains($fieldPath, '.*')) {
                $relationName = Str::before($fieldPath, '.*');
                $data[$relationName] = [
                    ['id' => 1, 'sample' => 'value'],
                ];

                continue;
            }

            if (Str::contains($fieldPath, '.')) {
                $segments = explode('.', $fieldPath);
                $topLevel = $segments[0];
                $data[$topLevel] = $data[$topLevel] ?? ['id' => 1, $segments[count($segments) - 1] => 'sample'];

                continue;
            }

            $data[$fieldPath] = $this->generateSampleValue($fieldPath, $model);
        }

        return $data;
    }

    protected function buildSampleFromAttributes(Model $model): array
    {
        $attributes = $this->schemaAnalyzer->getAttributeNames(get_class($model));
        $hidden = $model->getHidden();
        $excluded = config('filament-automation-bridge.field_schema.excluded_attributes', []);

        $data = [];

        foreach ($attributes as $attr) {
            $name = is_array($attr) ? $attr['name'] : $attr;

            if (in_array($name, $hidden) || in_array($name, $excluded)) {
                continue;
            }

            $data[$name] = $this->generateSampleValue($name, $model);
        }

        return $data;
    }

    protected function generateSampleValue(string $field, Model $model): mixed
    {
        $lower = strtolower($field);

        if (in_array($lower, ['id'])) {
            return 1;
        }

        if (Str::contains($lower, ['email'])) {
            return 'user@example.com';
        }

        if (Str::contains($lower, ['name'])) {
            return 'Sample Name';
        }

        if (Str::contains($lower, ['phone', 'telephone'])) {
            return '+1234567890';
        }

        if (Str::contains($lower, ['url', 'website', 'link'])) {
            return 'https://example.com';
        }

        if (Str::contains($lower, ['price', 'amount', 'total', 'cost', 'fee'])) {
            return 99.99;
        }

        if (Str::contains($lower, ['count', 'quantity', 'qty', 'number', 'stock'])) {
            return 10;
        }

        if (Str::contains($lower, ['is_', 'has_', 'enabled', 'active', 'visible'])) {
            return true;
        }

        if (in_array($lower, $model->getDates())) {
            return now()->toIso8601String();
        }

        if (Str::contains($lower, ['created_at', 'updated_at', 'deleted_at'])) {
            return now()->toIso8601String();
        }

        if (Str::contains($lower, ['image', 'photo', 'avatar', 'file', 'path'])) {
            return '/sample/path';
        }

        return 'sample';
    }

    protected function resolveFormatter(DestinationType $destinationType): ?PayloadFormatter
    {
        $formatters = app()->tagged('automation-bridge.formatters');

        foreach ($formatters as $formatter) {
            if ($formatter instanceof PayloadFormatter && $formatter->destinationType() === $destinationType) {
                return $formatter;
            }
        }

        $formatters = [
            ZapierFormatter::class,
            MakeFormatter::class,
            N8nFormatter::class,
            CustomFormatter::class,
        ];

        foreach ($formatters as $formatterClass) {
            $formatter = app($formatterClass);

            if ($formatter instanceof PayloadFormatter && $formatter->destinationType() === $destinationType) {
                return $formatter;
            }
        }

        return null;
    }
}
