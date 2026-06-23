<?php

namespace Ashrafic\FilamentAutomationBridge\Tests\Feature;

use Ashrafic\FilamentAutomationBridge\Tests\Fixtures\Models\AdminUser;
use Ashrafic\FilamentAutomationBridge\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

class FilamentTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        Gate::before(fn () => true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $admin = AdminUser::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $this->actingAs($admin, 'web');
    }
}
