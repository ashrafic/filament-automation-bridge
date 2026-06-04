<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Unit\Services;

use Ashrafic\FilamentWebhookBridge\Services\ModelDiscoveryService;
use Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models\TestOrder;
use Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models\TestUser;
use Ashrafic\FilamentWebhookBridge\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class ModelDiscoveryServiceTest extends TestCase
{
    protected ModelDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ModelDiscoveryService::class);
        Cache::flush();
    }

    public function test_is_valid_model_returns_true_for_real_eloquent_model(): void
    {
        $this->assertTrue($this->service->isValidModel(TestUser::class));
    }

    public function test_is_valid_model_returns_true_for_test_order(): void
    {
        $this->assertTrue($this->service->isValidModel(TestOrder::class));
    }

    public function test_is_valid_model_returns_false_for_nonexistent_class(): void
    {
        $this->assertFalse($this->service->isValidModel('App\\Models\\NonExistent'));
    }

    public function test_is_valid_model_returns_false_for_non_model_class(): void
    {
        $this->assertFalse($this->service->isValidModel(\stdClass::class));
    }

    public function test_is_valid_model_returns_false_for_abstract_model(): void
    {
        $this->assertFalse($this->service->isValidModel(Model::class));
    }

    public function test_resolve_model_returns_instance_for_valid_class(): void
    {
        $model = $this->service->resolveModel(TestUser::class);

        $this->assertInstanceOf(TestUser::class, $model);
    }

    public function test_resolve_model_returns_null_for_invalid_class(): void
    {
        $this->assertNull($this->service->resolveModel('App\\Models\\NonExistent'));
    }

    public function test_get_all_models_returns_array(): void
    {
        $result = $this->service->getAllModels();

        $this->assertIsArray($result);
    }

    public function test_caches_results(): void
    {
        Cache::flush();

        $result1 = $this->service->getAllModels();
        $result2 = $this->service->getAllModels();

        $this->assertSame($result1, $result2);
    }

    public function test_refresh_cache_clears_old_cache(): void
    {
        $result1 = $this->service->getAllModels();
        $this->assertIsArray($result1);

        $this->service->refreshCache();

        $result2 = $this->service->getAllModels();
        $this->assertIsArray($result2);
    }

    public function test_returns_empty_array_when_no_models_found(): void
    {
        config(['filament-webhook-bridge.models.paths' => ['/nonexistent/directory/that/does/not/exist']]);

        Cache::flush();
        $freshService = new ModelDiscoveryService;
        $result = $freshService->getAllModels();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}