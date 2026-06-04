<?php

namespace Ashrafic\FilamentWebhookBridge\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class ModelDiscoveryService
{
    public function getAllModels(): array
    {
        return Cache::remember(
            config('filament-webhook-bridge.models.cache_key', 'webhook_bridge.models'),
            config('filament-webhook-bridge.models.cache_ttl', 3600),
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
        Cache::forget(config('filament-webhook-bridge.models.cache_key', 'webhook_bridge.models'));

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
        $exclude = config('filament-webhook-bridge.models.exclude', []);

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
        $paths = config('filament-webhook-bridge.models.paths', [app_path('Models')]);

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
}
