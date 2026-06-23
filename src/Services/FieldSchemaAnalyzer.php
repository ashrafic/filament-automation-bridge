<?php

namespace Ashrafic\FilamentAutomationBridge\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionMethod;

class FieldSchemaAnalyzer
{
    public function analyze(string $modelClass, ?int $maxDepth = null, array $visited = []): array
    {
        $maxDepth = $maxDepth ?? config('filament-automation-bridge.field_schema.max_relation_depth', 3);

        $cacheKey = "automation_bridge.field_schema.{$modelClass}.{$maxDepth}";

        return Cache::remember(
            $cacheKey,
            config('filament-automation-bridge.field_schema.cache_ttl', 3600),
            fn () => $this->buildSchema($modelClass, $maxDepth, $visited)
        );
    }

    public function getAttributeNames(string $modelClass): array
    {
        $model = $this->resolveModel($modelClass);

        if ($model === null) {
            return [];
        }

        try {
            $table = $model->getTable();
            $columns = $model->getConnection()->getSchemaBuilder()->getColumnListing($table);
        } catch (\Throwable $e) {
            Log::warning('FieldSchemaAnalyzer: Unable to get column listing', [
                'model' => $modelClass,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $excluded = config('filament-automation-bridge.field_schema.excluded_attributes', [
            'password',
            'remember_token',
            'api_token',
        ]);

        $attributes = array_values(array_filter($columns, fn (string $column) => ! in_array($column, $excluded)));

        $modelReflection = new ReflectionClass($modelClass);
        $computed = [];

        foreach ($modelReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() !== $modelClass) {
                continue;
            }

            $methodName = $method->getName();

            if ($model->hasGetMutator($methodName) && ! in_array($methodName, $attributes)) {
                $computed[] = $methodName;
            }
        }

        foreach ($attributes as $i => $attr) {
            $attributes[$i] = ['name' => $attr, 'computed' => false];
        }

        foreach ($computed as $attr) {
            $attributes[] = ['name' => $attr, 'computed' => true];
        }

        return $attributes;
    }

    public function validateFieldPath(string $modelClass, string $fieldPath): bool
    {
        $segments = explode('.', $fieldPath);

        return $this->traverseFieldPath($modelClass, $segments);
    }

    public function getAttributeColumnType(string $modelClass, string $attribute): ?string
    {
        $model = $this->resolveModel($modelClass);

        if ($model === null) {
            return null;
        }

        try {
            $table = $model->getTable();
            $columns = $model->getConnection()->getSchemaBuilder()->getColumns($table);
        } catch (\Throwable $e) {
            Log::warning('FieldSchemaAnalyzer: Unable to get column types', [
                'model' => $modelClass,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        foreach ($columns as $column) {
            if (($column['name'] ?? null) === $attribute) {
                return $column['type'] ?? null;
            }
        }

        return null;
    }

    public function getRelations(string $modelClass): array
    {
        $model = $this->resolveModel($modelClass);

        if ($model === null) {
            return [];
        }

        $reflection = new ReflectionClass($modelClass);
        $relations = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() !== $modelClass) {
                continue;
            }

            $returnType = $method->getReturnType();

            if ($returnType === null) {
                continue;
            }

            $returnTypeName = $returnType->getName();

            if (! is_a($returnTypeName, Relation::class, true)) {
                continue;
            }

            $relationName = $method->getName();

            try {
                $relation = $model->$relationName();
            } catch (\Throwable $e) {
                Log::warning('FieldSchemaAnalyzer: Relation method threw exception', [
                    'model' => $modelClass,
                    'relation' => $relationName,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $type = $this->getRelationType($relation);

            if ($type === null) {
                continue;
            }

            if ($type === 'BelongsToMany') {
                continue;
            }

            $relatedModel = null;

            if ($relation instanceof MorphTo) {
                $relatedModel = 'polymorphic';
            } else {
                $relatedModel = get_class($relation->getRelated());
            }

            $relations[] = [
                'name' => $relationName,
                'type' => $type,
                'model' => $relatedModel,
            ];
        }

        return $relations;
    }

    protected function buildSchema(string $modelClass, int $maxDepth, array $visited): array
    {
        $model = $this->resolveModel($modelClass);

        if ($model === null) {
            return [
                'label' => class_basename($modelClass),
                'model' => $modelClass,
                'attributes' => [],
                'relations' => [],
            ];
        }

        $attributes = $this->getAttributeNames($modelClass);
        $relations = $this->getRelations($modelClass);
        $visited[] = $modelClass;

        $detailedRelations = [];

        foreach ($relations as $relation) {
            if ($relation['model'] === 'polymorphic') {
                $detailedRelations[] = array_merge($relation, [
                    'attributes' => [],
                    'relations' => [],
                ]);

                continue;
            }

            $relatedClass = $relation['model'];

            if ($maxDepth <= 0 || in_array($relatedClass, $visited)) {
                $detailedRelations[] = array_merge($relation, [
                    'attributes' => [],
                    'relations' => [],
                ]);

                continue;
            }

            $relatedSchema = $this->analyze($relatedClass, $maxDepth - 1, $visited);

            $detailedRelations[] = array_merge($relation, [
                'attributes' => $relatedSchema['attributes'],
                'relations' => $relatedSchema['relations'],
            ]);
        }

        return [
            'label' => class_basename($modelClass),
            'model' => $modelClass,
            'attributes' => $attributes,
            'relations' => $detailedRelations,
        ];
    }

    protected function resolveModel(string $modelClass): ?Model
    {
        if (! class_exists($modelClass)) {
            return null;
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        $reflection = new ReflectionClass($modelClass);

        if ($reflection->isAbstract()) {
            return null;
        }

        try {
            return new $modelClass;
        } catch (\Throwable $e) {
            Log::warning('FieldSchemaAnalyzer: Unable to instantiate model', [
                'model' => $modelClass,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function getRelationType(Relation $relation): ?string
    {
        $map = [
            BelongsTo::class => 'BelongsTo',
            HasOne::class => 'HasOne',
            HasMany::class => 'HasMany',
            BelongsToMany::class => 'BelongsToMany',
            MorphTo::class => 'MorphTo',
            MorphMany::class => 'MorphMany',
        ];

        foreach ($map as $class => $type) {
            if ($relation instanceof $class) {
                return $type;
            }
        }

        return null;
    }

    protected function traverseFieldPath(string $modelClass, array $segments): bool
    {
        if (empty($segments)) {
            return false;
        }

        $segment = array_shift($segments);

        $attributeNames = array_map(
            fn (array $attr) => $attr['name'],
            $this->getAttributeNames($modelClass)
        );

        if (empty($segments)) {
            return in_array($segment, $attributeNames);
        }

        $relations = $this->getRelations($modelClass);

        foreach ($relations as $relation) {
            if ($relation['name'] === $segment) {
                if ($relation['model'] === 'polymorphic') {
                    return false;
                }

                return $this->traverseFieldPath($relation['model'], $segments);
            }
        }

        return false;
    }
}
