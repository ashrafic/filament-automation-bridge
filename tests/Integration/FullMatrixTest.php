<?php

namespace Ashrafic\FilamentAutomationBridge\Tests\Integration;

use Ashrafic\FilamentAutomationBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Ashrafic\FilamentAutomationBridge\Tests\Fixtures\Models\TestUser;
use Ashrafic\FilamentAutomationBridge\Tests\TestCase;
use Ashrafic\FilamentAutomationBridge\Triggers\StatusChangedTrigger;
use Ashrafic\FilamentAutomationBridge\Triggers\TriggerManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class FullMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('filament-automation-bridge.sandbox_mode', true);
    }

    protected function createTrigger(array $overrides = []): AutomationTrigger
    {
        return AutomationTrigger::create(array_merge([
            'name' => 'Matrix Test',
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'trigger_type' => 'model-event',
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'destination_type' => DestinationType::Custom,
            'destination_url' => 'https://httpbin.org/post',
            'payload_mode' => PayloadMode::Summary,
            'field_mapping' => ['name', 'email'],
            'active' => true,
            'max_retries' => 3,
            'request_timeout' => 5,
        ], $overrides));
    }

    protected function assertDispatched(): void
    {
        $d = AutomationDelivery::latest()->first();
        $this->assertNotNull($d);
        $this->assertSame(DeliveryStatus::Success, $d->status);
    }

    // ─── ON EVENT: All 5 EventEnum values ─────────────────────────────

    public function test_on_event_created_dispatches(): void
    {
        $this->createTrigger(['event' => EventEnum::Created, 'trigger_config' => ['event' => 'created', 'watch_fields' => []]]);
        TestUser::create(['name' => 'EC', 'email' => 'ec@test.com']);
        $this->assertDispatched();
    }

    public function test_on_event_updated_dispatches(): void
    {
        $user = TestUser::create(['name' => 'EU', 'email' => 'eu@test.com']);
        $this->createTrigger(['event' => EventEnum::Updated, 'trigger_config' => ['event' => 'updated', 'watch_fields' => []]]);
        $user->update(['name' => 'EU Updated']);
        $this->assertDispatched();
    }

    public function test_on_event_deleted_dispatches(): void
    {
        $user = TestUser::create(['name' => 'ED', 'email' => 'ed@test.com']);
        $this->createTrigger(['event' => EventEnum::Deleted, 'trigger_config' => ['event' => 'deleted', 'watch_fields' => []]]);
        $user->delete();
        $this->assertDispatched();
    }

    public function test_on_event_restored_dispatches(): void
    {
        $user = TestUser::create(['name' => 'ER', 'email' => 'er@test.com']);
        $user->delete();
        $this->createTrigger(['event' => EventEnum::Restored, 'trigger_config' => ['event' => 'restored', 'watch_fields' => []]]);
        $user->restore();
        $this->assertDispatched();
    }

    public function test_on_event_force_deleted_dispatches(): void
    {
        $user = TestUser::create(['name' => 'EF', 'email' => 'ef@test.com']);
        $this->createTrigger(['event' => EventEnum::ForceDeleted, 'trigger_config' => ['event' => 'force_deleted', 'watch_fields' => []]]);
        $user->forceDelete();
        $this->assertDispatched();
    }

    // ─── SCHEDULE TYPE: All 5 schedule_type values ─────────────────────

    public function test_schedule_type_hourly_dispatches(): void
    {
        $user = TestUser::create(['name' => 'SH', 'email' => 'sh@test.com']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'hourly'],
        ]);
        $ds = app(DeliveryService::class);
        $d = $ds->dispatchForSchedule($trigger, $user);
        $this->assertNotNull($d);
        $this->assertSame('hourly', $d->payload['trigger_context']['schedule_type']);
    }

    public function test_schedule_type_daily_dispatches(): void
    {
        $user = TestUser::create(['name' => 'SD', 'email' => 'sd@test.com']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'daily'],
        ]);
        $ds = app(DeliveryService::class);
        $d = $ds->dispatchForSchedule($trigger, $user);
        $this->assertNotNull($d);
        $this->assertSame('daily', $d->payload['trigger_context']['schedule_type']);
    }

    public function test_schedule_type_weekly_dispatches(): void
    {
        $user = TestUser::create(['name' => 'SW', 'email' => 'sw@test.com']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'weekly'],
        ]);
        $ds = app(DeliveryService::class);
        $d = $ds->dispatchForSchedule($trigger, $user);
        $this->assertNotNull($d);
        $this->assertSame('weekly', $d->payload['trigger_context']['schedule_type']);
    }

    public function test_schedule_type_monthly_dispatches(): void
    {
        $user = TestUser::create(['name' => 'SM', 'email' => 'sm@test.com']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'monthly'],
        ]);
        $ds = app(DeliveryService::class);
        $d = $ds->dispatchForSchedule($trigger, $user);
        $this->assertNotNull($d);
        $this->assertSame('monthly', $d->payload['trigger_context']['schedule_type']);
    }

    public function test_schedule_type_custom_dispatches(): void
    {
        $user = TestUser::create(['name' => 'SC', 'email' => 'sc@test.com']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'custom', 'custom_cron' => '0 9 * * *'],
        ]);
        $ds = app(DeliveryService::class);
        $d = $ds->dispatchForSchedule($trigger, $user);
        $this->assertNotNull($d);
        $this->assertSame('custom', $d->payload['trigger_context']['schedule_type']);
    }

    // ─── SCHEDULE TYPE + CONDITIONS ────────────────────────────────────

    public function test_schedule_hourly_with_condition(): void
    {
        $user = TestUser::create(['name' => 'SHC', 'email' => 'shc@test.com', 'status' => 'premium']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'hourly'],
            'conditions' => [['field' => 'status', 'operator' => 'equals', 'value' => 'premium']],
        ]);
        $d = app(DeliveryService::class)->dispatchForSchedule($trigger, $user);
        $this->assertNotNull($d);
    }

    public function test_schedule_daily_with_condition(): void
    {
        $user = TestUser::create(['name' => 'SDC', 'email' => 'sdc@test.com', 'score' => 100]);
        $trigger = $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'daily'],
            'conditions' => [['field' => 'score', 'operator' => 'greater_than', 'value' => '50']],
        ]);
        $d = app(DeliveryService::class)->dispatchForSchedule($trigger, $user);
        $this->assertNotNull($d);
    }

    public function test_schedule_weekly_with_condition(): void
    {
        $user = TestUser::create(['name' => 'SWC', 'email' => 'swc@test.com', 'is_visible' => true]);
        $trigger = $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'weekly'],
            'conditions' => [['field' => 'is_visible', 'operator' => 'equals', 'value' => '1']],
        ]);
        $d = app(DeliveryService::class)->dispatchForSchedule($trigger, $user);
        $this->assertNotNull($d);
    }

    // ─── STATUS CHANGED: from_status combinations ──────────────────────

    public function test_status_changed_from_any_to_specific(): void
    {
        $user = TestUser::create(['name' => 'SCA', 'email' => 'sca@test.com', 'status' => 'pending']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'status-changed',
            'event' => EventEnum::Updated,
            'trigger_config' => ['status_field' => 'status', 'from_status' => null, 'to_status' => 'active'],
        ]);
        $user->update(['status' => 'active']);
        $d = app(DeliveryService::class)->dispatch($trigger, $user, EventEnum::Updated, $user->getOriginal(), [
            'previous_status' => 'pending', 'new_status' => 'active',
        ]);
        $this->assertNotNull($d);
    }

    public function test_status_changed_from_specific_to_specific(): void
    {
        $user = TestUser::create(['name' => 'SCS', 'email' => 'scs@test.com', 'status' => 'pending']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'status-changed',
            'event' => EventEnum::Updated,
            'trigger_config' => ['status_field' => 'status', 'from_status' => 'pending', 'to_status' => 'shipped'],
        ]);
        $user->update(['status' => 'shipped']);
        $d = app(DeliveryService::class)->dispatch($trigger, $user, EventEnum::Updated, $user->getOriginal(), [
            'previous_status' => 'pending', 'new_status' => 'shipped',
        ]);
        $this->assertNotNull($d);
    }

    public function test_status_changed_does_not_fire_when_from_status_mismatch(): void
    {
        $user = TestUser::create(['name' => 'SCM', 'email' => 'scm@test.com', 'status' => 'active']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'status-changed',
            'event' => EventEnum::Updated,
            'trigger_config' => ['status_field' => 'status', 'from_status' => 'pending', 'to_status' => 'shipped'],
        ]);
        $user->update(['status' => 'shipped']);
        $sc = new StatusChangedTrigger;
        $result = $sc->shouldFire($user, $trigger->trigger_config, ['event' => 'updated']);
        $this->assertFalse($result, 'Should not fire when from_status does not match previous value');
    }

    public function test_status_changed_does_not_fire_when_to_status_mismatch(): void
    {
        $user = TestUser::create(['name' => 'SCT', 'email' => 'sct@test.com', 'status' => 'pending']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'status-changed',
            'event' => EventEnum::Updated,
            'trigger_config' => ['status_field' => 'status', 'from_status' => 'pending', 'to_status' => 'shipped'],
        ]);
        $user->update(['status' => 'cancelled']);
        $sc = new StatusChangedTrigger;
        $result = $sc->shouldFire($user, $trigger->trigger_config, ['event' => 'updated']);
        $this->assertFalse($result, 'Should not fire when to_status does not match new value');
    }

    public function test_status_changed_does_not_fire_when_field_not_changed(): void
    {
        $user = TestUser::create(['name' => 'SCN', 'email' => 'scn@test.com', 'status' => 'active', 'score' => 0]);
        $trigger = $this->createTrigger([
            'trigger_type' => 'status-changed',
            'event' => EventEnum::Updated,
            'trigger_config' => ['status_field' => 'status', 'from_status' => null, 'to_status' => 'active'],
        ]);
        $user->update(['score' => 50]);
        $sc = new StatusChangedTrigger;
        $result = $sc->shouldFire($user, $trigger->trigger_config, ['event' => 'updated']);
        $this->assertFalse($result, 'Should not fire when status field did not change');
    }

    public function test_status_changed_with_custom_field_name(): void
    {
        $user = TestUser::create(['name' => 'SCX', 'email' => 'scx@test.com', 'score' => 10]);
        $trigger = $this->createTrigger([
            'trigger_type' => 'status-changed',
            'event' => EventEnum::Updated,
            'trigger_config' => ['status_field' => 'score', 'from_status' => null, 'to_status' => '50'],
        ]);
        $user->update(['score' => 50]);
        $d = app(DeliveryService::class)->dispatch($trigger, $user, EventEnum::Updated, $user->getOriginal(), [
            'previous_status' => 10, 'new_status' => 50,
        ]);
        $this->assertNotNull($d);
    }

    // ─── MANUAL TRIGGER: various combinations ──────────────────────────

    public function test_manual_triggers_sends_payload_with_user_id(): void
    {
        $user = TestUser::create(['name' => 'MU', 'email' => 'mu@test.com']);
        $trigger = $this->createTrigger(['trigger_type' => 'manual', 'trigger_config' => []]);
        $d = app(DeliveryService::class)->dispatchForManualTrigger($trigger, $user, userId: 42);
        $this->assertNotNull($d);
        $this->assertSame(42, $d->payload['trigger_context']['user_id']);
        $this->assertSame('manual', $d->payload['trigger_context']['trigger_source']);
    }

    public function test_manual_trigger_without_user_id(): void
    {
        $user = TestUser::create(['name' => 'MN', 'email' => 'mn@test.com']);
        $trigger = $this->createTrigger(['trigger_type' => 'manual', 'trigger_config' => []]);
        $d = app(DeliveryService::class)->dispatchForManualTrigger($trigger, $user);
        $this->assertNotNull($d);
        $this->assertNull($d->payload['trigger_context']['user_id']);
    }

    public function test_manual_trigger_with_changed_condition_on_update(): void
    {
        $user = TestUser::create(['name' => 'MC', 'email' => 'mc@test.com', 'status' => 'active']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'manual',
            'trigger_config' => [],
            'conditions' => [['field' => 'status', 'operator' => 'equals', 'value' => 'active']],
        ]);
        $d = app(DeliveryService::class)->dispatchForManualTrigger($trigger, $user, userId: 1);
        $this->assertNotNull($d);
    }

    // ─── DATE CONDITION: all condition_type values ──────────────────────

    public function test_date_condition_before_dispatches(): void
    {
        $user = TestUser::create(['name' => 'DCB', 'email' => 'dcb@test.com']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'date-condition',
            'trigger_config' => ['date_field' => 'created_at', 'condition_type' => 'before', 'days' => 7],
        ]);
        $d = app(DeliveryService::class)->dispatchForDateCondition($trigger, $user, [
            'date_field' => 'created_at', 'date_value' => $user->created_at, 'condition' => 'before', 'days' => 7,
        ]);
        $this->assertNotNull($d);
    }

    public function test_date_condition_after_dispatches(): void
    {
        $user = TestUser::create(['name' => 'DCA', 'email' => 'dca@test.com']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'date-condition',
            'trigger_config' => ['date_field' => 'created_at', 'condition_type' => 'after', 'days' => 14],
        ]);
        $d = app(DeliveryService::class)->dispatchForDateCondition($trigger, $user, [
            'date_field' => 'created_at', 'date_value' => $user->created_at, 'condition' => 'after', 'days' => 14,
        ]);
        $this->assertNotNull($d);
    }

    public function test_date_condition_on_dispatches(): void
    {
        $user = TestUser::create(['name' => 'DCO', 'email' => 'dco@test.com']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'date-condition',
            'trigger_config' => ['date_field' => 'created_at', 'condition_type' => 'on', 'days' => 0],
        ]);
        $d = app(DeliveryService::class)->dispatchForDateCondition($trigger, $user, [
            'date_field' => 'created_at', 'date_value' => $user->created_at, 'condition' => 'on', 'days' => 0,
        ]);
        $this->assertNotNull($d);
    }

    // ─── EVENT TRIGGER: class matching + conditions ────────────────────

    public function test_event_trigger_fires_for_matching_class(): void
    {
        $user = TestUser::create(['name' => 'EVT', 'email' => 'evt@test.com']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'event',
            'trigger_config' => ['event_class' => 'App\\Events\\UserRegistered'],
        ]);
        TriggerManager::addSubscribedEvent('App\\Events\\UserRegistered', $trigger->id);
        $d = app(DeliveryService::class)->dispatchForEventTrigger($trigger, $user, ['event_class' => 'App\\Events\\UserRegistered']);
        $this->assertNotNull($d);
    }

    public function test_event_trigger_with_boolean_condition(): void
    {
        $user = TestUser::create(['name' => 'EVB', 'email' => 'evb@test.com', 'is_visible' => false]);
        $trigger = $this->createTrigger([
            'trigger_type' => 'event',
            'trigger_config' => ['event_class' => 'App\\Events\\HiddenUser'],
            'conditions' => [['field' => 'is_visible', 'operator' => 'equals', 'value' => '0']],
        ]);
        TriggerManager::addSubscribedEvent('App\\Events\\HiddenUser', $trigger->id);
        $d = app(DeliveryService::class)->dispatchForEventTrigger($trigger, $user, ['event_class' => 'App\\Events\\HiddenUser']);
        $this->assertNotNull($d);
    }

    public function test_event_trigger_with_changed_condition(): void
    {
        $user = TestUser::create(['name' => 'EVC', 'email' => 'evc@test.com', 'status' => 'active']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'event',
            'trigger_config' => ['event_class' => 'App\\Events\\StatusEvent'],
            'conditions' => [['field' => 'status', 'operator' => 'equals', 'value' => 'active']],
        ]);
        TriggerManager::addSubscribedEvent('App\\Events\\StatusEvent', $trigger->id);
        $d = app(DeliveryService::class)->dispatchForEventTrigger($trigger, $user, ['event_class' => 'App\\Events\\StatusEvent']);
        $this->assertNotNull($d);
    }

    // ─── COMBINED: trigger_type + condition operator matrix ────────────

    public function test_schedule_monthly_contains_operator(): void
    {
        $user = TestUser::create(['name' => 'SMo', 'email' => 'smo@test.com', 'name' => 'PremiumMember']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'monthly'],
            'conditions' => [['field' => 'name', 'operator' => 'contains', 'value' => 'Premium']],
        ]);
        $d = app(DeliveryService::class)->dispatchForSchedule($trigger, $user);
        $this->assertNotNull($d);
    }

    public function test_manual_not_equals_operator(): void
    {
        $user = TestUser::create(['name' => 'MNE', 'email' => 'mne@test.com', 'status' => 'active']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'manual',
            'trigger_config' => [],
            'conditions' => [['field' => 'status', 'operator' => 'not_equals', 'value' => 'banned']],
        ]);
        $d = app(DeliveryService::class)->dispatchForManualTrigger($trigger, $user, userId: 1);
        $this->assertNotNull($d);
    }

    public function test_date_condition_is_not_empty_operator(): void
    {
        $user = TestUser::create(['name' => 'DCN', 'email' => 'dcn@test.com', 'status' => 'premium']);
        $trigger = $this->createTrigger([
            'trigger_type' => 'date-condition',
            'trigger_config' => ['date_field' => 'created_at', 'condition_type' => 'before', 'days' => 7],
            'conditions' => [['field' => 'status', 'operator' => 'is_not_empty']],
        ]);
        $d = app(DeliveryService::class)->dispatchForDateCondition($trigger, $user, [
            'date_field' => 'created_at', 'date_value' => $user->created_at, 'condition' => 'before', 'days' => 7,
        ]);
        $this->assertNotNull($d);
    }

    public function test_dispatch_for_schedule_respects_conditions_column(): void
    {
        $premium = TestUser::create(['name' => 'PremS', 'email' => 'prems@test.com', 'status' => 'premium']);
        $regular = TestUser::create(['name' => 'RegS', 'email' => 'regs@test.com', 'status' => 'active']);

        $trigger = $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'daily'],
            'conditions' => [['field' => 'status', 'operator' => 'equals', 'value' => 'premium']],
        ]);

        $ds = app(DeliveryService::class);

        $d1 = $ds->dispatchForSchedule($trigger, $premium);
        $this->assertNotNull($d1, 'Should dispatch for premium user');

        $d2 = $ds->dispatchForSchedule($trigger, $regular);
        $this->assertNull($d2, 'Should skip for regular user');
    }
}
