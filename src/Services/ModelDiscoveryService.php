<?php

namespace Ashrafic\FilamentAutomationBridge\Services;

use Ashrafic\FilamentAutomationBridge\Concerns\HasAutomationTriggers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class ModelDiscoveryService
{
    public function getAllModels(): array
    {
        return Cache::remember(
            config('filament-automation-bridge.models.cache_key', 'automation_bridge.models'),
            config('filament-automation-bridge.models.cache_ttl', 3600),
            fn () => $this->discoverModels()
        );
    }

    public function isValidModel(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        if (! is_subclass_of($class, Model::class)) {
            return false;
        }

        return (new ReflectionClass($class))->isAbstract() === false;
    }

    public function refreshCache(): void
    {
        Cache::forget(config('filament-automation-bridge.models.cache_key', 'automation_bridge.models'));

        $this->getAllModels();
    }

    public function resolveModel(string $class): ?Model
    {
        if (! $this->isValidModel($class)) {
            return null;
        }

        return new $class;
    }

    protected function discoverModels(): array
    {
        $models = [];
        $exclude = config('filament-automation-bridge.models.exclude', []);

        foreach ($this->scanPaths() as $fqcn => $basename) {
            if (in_array($fqcn, $exclude)) {
                continue;
            }

            $models[$fqcn] = $basename;
        }

        uasort($models, fn (string $a, string $b) => $a <=> $b);

        return $models;
    }

    protected function scanPaths(): \Generator
    {
        $paths = config('filament-automation-bridge.models.paths', [app_path('Models')]);

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $fqcn = $this->classFromFile($file);

                if ($fqcn === null) {
                    continue;
                }

                if (! $this->isValidModel($fqcn)) {
                    continue;
                }

                yield $fqcn => class_basename($fqcn);
            }
        }
    }

    protected function classFromFile(\SplFileInfo $file): ?string
    {
        $contents = @file_get_contents($file->getRealPath());

        if ($contents === false) {
            logger()->warning('Unable to read model file', ['path' => $file->getRealPath()]);

            return null;
        }

        if (! preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            logger()->warning('Model file has no namespace', ['path' => $file->getRealPath()]);

            return null;
        }

        $namespace = $matches[1];
        $classname = $file->getBasename('.php');
        $fqcn = $namespace.'\\'.$classname;

        return $fqcn;
    }

    public function discoverTriggerableModels(): array
    {
        $models = $this->getAllModels();
        $result = [];

        foreach ($models as $fqcn => $basename) {
            if (class_exists($fqcn) && $this->hasAutomationTriggersTrait($fqcn)) {
                $result[$fqcn] = static::getModelDisplayName($fqcn);
            }
        }

        return $result;
    }

    protected function hasAutomationTriggersTrait(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $traits = class_uses_recursive($class);

        return in_array(HasAutomationTriggers::class, $traits);
    }

    public static function getModelDisplayName(string $class): string
    {
        if (class_exists($class) && method_exists($class, 'getAutomationDisplayName')) {
            return $class::getAutomationDisplayName();
        }

        return class_basename($class);
    }
}
