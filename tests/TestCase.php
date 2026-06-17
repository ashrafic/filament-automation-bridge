<?php

namespace Ashrafic\FilamentAutomationBridge\Tests;

use Ashrafic\FilamentAutomationBridge\FilamentAutomationBridgeServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            FilamentAutomationBridgeServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestTables();
    }

    protected function setUpTestTables(): void
    {
        Schema::create('test_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('status')->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('test_orders', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('test_users')->cascadeOnDelete();
            $table->float('total')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('test_order_items', function ($table) {
            $table->id();
            $table->foreignId('order_id')->constrained('test_orders')->cascadeOnDelete();
            $table->string('name');
            $table->float('price')->default(0);
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('test_profiles', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('test_users')->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('test_leads', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('source')->default('organic');
            $table->string('status')->default('new');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    protected function createTestUser(array $attributes = []): Fixtures\Models\TestUser
    {
        return Fixtures\Models\TestUser::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ], $attributes));
    }

    protected function createTestOrder(array $attributes = []): Fixtures\Models\TestOrder
    {
        return Fixtures\Models\TestOrder::create(array_merge([
            'user_id' => $this->createTestUser()->id,
            'total' => 99.99,
            'status' => 'pending',
        ], $attributes));
    }

    protected function createTestOrderItem(array $attributes = []): Fixtures\Models\TestOrderItem
    {
        return Fixtures\Models\TestOrderItem::create(array_merge([
            'order_id' => $this->createTestOrder()->id,
            'name' => 'Test Item',
            'price' => 19.99,
            'quantity' => 1,
        ], $attributes));
    }

    protected function createTestProfile(array $attributes = []): Fixtures\Models\TestProfile
    {
        return Fixtures\Models\TestProfile::create(array_merge([
            'user_id' => $this->createTestUser()->id,
            'bio' => 'Test bio',
        ], $attributes));
    }

    protected function createTestLead(array $attributes = []): Fixtures\Models\TestLead
    {
        return Fixtures\Models\TestLead::create(array_merge([
            'name' => 'Test Lead',
            'email' => 'lead@example.com',
        ], $attributes));
    }
}
