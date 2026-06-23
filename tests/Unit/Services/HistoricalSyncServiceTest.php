<?php

namespace Ashrafic\FilamentAutomationBridge\Tests\Unit\Services;

use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\HistoricalSyncService;
use Ashrafic\FilamentAutomationBridge\Tests\Fixtures\Models\TestUser;
use Ashrafic\FilamentAutomationBridge\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HistoricalSyncServiceTest extends TestCase
{
    protected HistoricalSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(HistoricalSyncService::class);
        Cache::flush();
    }

    protected function createTrigger(array $overrides = []): AutomationTrigger
    {
        return AutomationTrigger::create(array_merge([
            'name' => 'Test Trigger',
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Custom,
            'destination_url' => 'https://example.com/webhook',
            'field_mapping' => ['name', 'email'],
            'payload_mode' => PayloadMode::Summary,
            'active' => true,
            'max_retries' => 3,
            'request_timeout' => 5,
        ], $overrides));
    }

    public function test_get_progress_returns_not_found_for_unknown_uuid(): void
    {
        $progress = $this->service->getProgress('non-existent-uuid');

        $this->assertSame('not_found', $progress['status']);
        $this->assertSame(0, $progress['total']);
        $this->assertSame(0, $progress['processed']);
    }

    public function test_cancel_sync_returns_false_for_unknown_uuid(): void
    {
        $result = $this->service->cancelSync('non-existent-uuid');

        $this->assertFalse($result);
    }

    public function test_cancel_sync_sets_status_to_cancelled(): void
    {
        $batchUuid = 'test-batch-uuid';

        Cache::put("automation_bridge.sync.{$batchUuid}", [
            'status' => 'in_progress',
            'total' => 10,
            'processed' => 3,
            'successful' => 2,
            'failed' => 1,
            'trigger_id' => 1,
            'apply_conditions' => false,
        ], now()->addDays(7));

        $result = $this->service->cancelSync($batchUuid);

        $this->assertTrue($result);

        $progress = $this->service->getProgress($batchUuid);
        $this->assertSame('cancelled', $progress['status']);
    }

    public function test_cancel_sync_returns_false_for_completed_batch(): void
    {
        $batchUuid = 'completed-batch-uuid';

        Cache::put("automation_bridge.sync.{$batchUuid}", [
            'status' => 'completed',
            'total' => 10,
            'processed' => 10,
            'successful' => 10,
            'failed' => 0,
            'trigger_id' => 1,
            'apply_conditions' => false,
        ], now()->addDays(7));

        $result = $this->service->cancelSync($batchUuid);

        $this->assertFalse($result);
    }

    public function test_cancel_sync_returns_false_for_already_cancelled(): void
    {
        $batchUuid = 'already-cancelled-uuid';

        Cache::put("automation_bridge.sync.{$batchUuid}", [
            'status' => 'cancelled',
            'total' => 10,
            'processed' => 3,
            'successful' => 2,
            'failed' => 1,
            'trigger_id' => 1,
            'apply_conditions' => false,
        ], now()->addDays(7));

        $result = $this->service->cancelSync($batchUuid);

        $this->assertFalse($result);
    }

    public function test_get_progress_returns_stored_progress(): void
    {
        $batchUuid = 'progress-test-uuid';

        Cache::put("automation_bridge.sync.{$batchUuid}", [
            'status' => 'in_progress',
            'total' => 20,
            'processed' => 5,
            'successful' => 4,
            'failed' => 1,
            'trigger_id' => 1,
            'apply_conditions' => false,
        ], now()->addDays(7));

        $progress = $this->service->getProgress($batchUuid);

        $this->assertSame('in_progress', $progress['status']);
        $this->assertSame(20, $progress['total']);
        $this->assertSame(5, $progress['processed']);
        $this->assertSame(4, $progress['successful']);
        $this->assertSame(1, $progress['failed']);
    }

    public function test_start_sync_returns_uuid_and_creates_cache_entry(): void
    {
        $this->createTestUser(['name' => 'User 1']);

        $trigger = $this->createTrigger();

        $uuid = $this->service->startSync($trigger);

        $this->assertIsString($uuid);
        $this->assertTrue(Str::isUuid($uuid) || strlen($uuid) === 36);

        $progress = $this->service->getProgress($uuid);
        $this->assertSame('completed', $progress['status']);
        $this->assertSame(1, $progress['total']);
    }

    public function test_start_sync_throws_for_invalid_model_class(): void
    {
        $trigger = $this->createTrigger(['model_class' => 'App\\Models\\NonExistent']);

        $this->expectException(\Ashrafic\FilamentAutomationBridge\Exceptions\ModelNotFoundException::class);
        $this->service->startSync($trigger);
    }

    public function test_get_progress_calculates_eta(): void
    {
        $batchUuid = 'eta-test-uuid';

        Cache::put("automation_bridge.sync.{$batchUuid}", [
            'status' => 'in_progress',
            'total' => 100,
            'processed' => 25,
            'successful' => 20,
            'failed' => 5,
            'trigger_id' => 1,
            'apply_conditions' => false,
        ], now()->addDays(7));

        $progress = $this->service->getProgress($batchUuid);

        $this->assertArrayHasKey('eta_seconds', $progress);
        $this->assertIsInt($progress['eta_seconds']);
        $this->assertGreaterThanOrEqual(0, $progress['eta_seconds']);
    }
}
