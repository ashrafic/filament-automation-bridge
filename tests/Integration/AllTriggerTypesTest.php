<?php

namespace Ashrafic\FilamentAutomationBridge\Tests\Integration;

use Ashrafic\FilamentAutomationBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Ashrafic\FilamentAutomationBridge\Tests\Fixtures\Models\TestOrder;
use Ashrafic\FilamentAutomationBridge\Tests\Fixtures\Models\TestUser;
use Ashrafic\FilamentAutomationBridge\Tests\TestCase;
use Ashrafic\FilamentAutomationBridge\Triggers\TriggerManager;
use Illuminate\Support\Facades\Config;

class AllTriggerTypesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filament-automation-bridge.sandbox_mode', true);
    }

    protected function createTrigger(array $overrides = []): AutomationTrigger
    {
        return AutomationTrigger::create(array_merge([
            'name' => 'AllTypes Test',
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

    protected function assertDeliveryDispatched(): AutomationDelivery
    {
        $delivery = AutomationDelivery::where('source', 'realtime')->latest()->first();

        $this->assertNotNull($delivery, 'Expected delivery to be created');
        $this->assertSame(DeliveryStatus::Success, $delivery->status, 'Delivery should be success in sandbox');

        return $delivery;
    }

    protected function assertNoDeliveryDispatched(): void
    {
        $delivery = AutomationDelivery::where('source', 'realtime')->latest()->first();

        $this->assertNull($delivery, 'Expected NO delivery to be created');
    }

    // ─── STATUS CHANGED TRIGGER ────────────────────────────────────────

    public function test_status_changed_without_conditions_dispatches(): void
    {
        $user = TestUser::create(['name' => 'SC', 'email' => 'sc@test.com', 'status' => 'pending']);

        $trigger = $this->createTrigger([
            'trigger_type' => 'status-changed',
            'event' => EventEnum::Updated,
            'trigger_config' => [
                'status_field' => 'status',
                'from_status' => null,
                'to_status' => 'shipped',
            ],
            'conditions' => null,
        ]);

        $user->update(['status' => 'shipped']);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatch(
            $trigger,
            $user,
            EventEnum::Updated,
            $user->getOriginal(),
            ['previous_status' => 'pending', 'new_status' => 'shipped'],
        );

        $this->assertNotNull($delivery);
    }

    public function test_status_changed_with_condition_equals_dispatches(): void
    {
        $user = TestUser::create(['name' => 'SC-EQ', 'email' => 'sceq@test.com', 'status' => 'pending']);

        $trigger = $this->createTrigger([
            'trigger_type' => 'status-changed',
            'event' => EventEnum::Updated,
            'trigger_config' => [
                'status_field' => 'status',
                'from_status' => null,
                'to_status' => 'active',
            ],
            'conditions' => [
                ['field' => 'score', 'operator' => 'greater_than', 'value' => '50'],
            ],
        ]);

        $user->update(['status' => 'active', 'score' => 100]);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatch(
            $trigger,
            $user,
            EventEnum::Updated,
            $user->getOriginal(),
            ['previous_status' => 'pending', 'new_status' => 'active'],
        );

        $this->assertNotNull($delivery);
    }

    public function test_status_changed_with_condition_skips_when_not_matching(): void
    {
        $user = TestUser::create(['name' => 'SC-SK', 'email' => 'scsk@test.com', 'status' => 'pending', 'score' => 25]);

        $trigger = $this->createTrigger([
            'trigger_type' => 'status-changed',
            'event' => EventEnum::Updated,
            'trigger_config' => [
                'status_field' => 'status',
                'from_status' => null,
                'to_status' => 'active',
            ],
            'conditions' => [
                ['field' => 'score', 'operator' => 'greater_than', 'value' => '50'],
            ],
        ]);

        $user->update(['status' => 'active']);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatch(
            $trigger,
            $user,
            EventEnum::Updated,
            $user->getOriginal(),
            ['previous_status' => 'pending', 'new_status' => 'active'],
        );

        $this->assertNull($delivery);
    }

    // ─── EVENT TRIGGER (full pipeline via Laravel event dispatch) ──────

    public function test_event_trigger_without_conditions_dispatches(): void
    {
        $user = TestUser::create(['name' => 'EV', 'email' => 'ev@test.com']);

        $trigger = $this->createTrigger([
            'trigger_type' => 'event',
            'trigger_config' => ['event_class' => 'test.event.fired'],
            'conditions' => null,
            'model_class' => TestUser::class,
        ]);

        TriggerManager::addSubscribedEvent('test.event.fired', $trigger->id);

        event('test.event.fired', [new \stdClass]);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatchForEventTrigger($trigger, $user, ['event_class' => 'test.event.fired']);

        $this->assertNotNull($delivery);
        $this->assertSame('test.event.fired', $delivery->payload['trigger_context']['event_class']);
    }

    public function test_event_trigger_with_condition_equals_dispatches(): void
    {
        $user = TestUser::create(['name' => 'EV-EQ', 'email' => 'eveq@test.com', 'status' => 'premium']);

        $trigger = $this->createTrigger([
            'trigger_type' => 'event',
            'trigger_config' => ['event_class' => 'test.event.premium'],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
            'model_class' => TestUser::class,
        ]);

        TriggerManager::addSubscribedEvent('test.event.premium', $trigger->id);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatchForEventTrigger($trigger, $user, ['event_class' => 'test.event.premium']);

        $this->assertNotNull($delivery);
    }

    public function test_event_trigger_with_condition_skips_when_not_matching(): void
    {
        $user = TestUser::create(['name' => 'EV-SK', 'email' => 'evsk@test.com', 'status' => 'active']);

        $trigger = $this->createTrigger([
            'trigger_type' => 'event',
            'trigger_config' => ['event_class' => 'test.event.skip'],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
            'model_class' => TestUser::class,
        ]);

        TriggerManager::addSubscribedEvent('test.event.skip', $trigger->id);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatchForEventTrigger($trigger, $user, ['event_class' => 'test.event.skip']);

        $this->assertNull($delivery);
    }

    // ─── SCHEDULE TRIGGER ──────────────────────────────────────────────

    public function test_schedule_trigger_without_conditions_dispatches(): void
    {
        $user = TestUser::create(['name' => 'SCH', 'email' => 'sch@test.com']);

        $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'daily'],
            'conditions' => null,
            'model_class' => TestUser::class,
        ]);

        $deliveryService = app(DeliveryService::class);
        $trigger = AutomationTrigger::active()->where('trigger_type', 'schedule')->first();
        $delivery = $deliveryService->dispatchForSchedule($trigger, $user);

        $this->assertNotNull($delivery);
        $this->assertSame('daily', $delivery->payload['trigger_context']['schedule_type']);
    }

    public function test_schedule_trigger_with_condition_equals_dispatches(): void
    {
        $user = TestUser::create(['name' => 'SCH-EQ', 'email' => 'scheq@test.com', 'status' => 'premium']);

        $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'weekly'],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
            'model_class' => TestUser::class,
        ]);

        $deliveryService = app(DeliveryService::class);
        $trigger = AutomationTrigger::active()->where('trigger_type', 'schedule')->first();
        $delivery = $deliveryService->dispatchForSchedule($trigger, $user);

        $this->assertNotNull($delivery);
    }

    public function test_schedule_trigger_with_condition_skips_when_not_matching(): void
    {
        $user = TestUser::create(['name' => 'SCH-SK', 'email' => 'schsk@test.com', 'status' => 'active']);

        $this->createTrigger([
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'daily'],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
            'model_class' => TestUser::class,
        ]);

        $deliveryService = app(DeliveryService::class);
        $trigger = AutomationTrigger::active()->where('trigger_type', 'schedule')->first();
        $delivery = $deliveryService->dispatchForSchedule($trigger, $user);

        $this->assertNull($delivery);
    }

    // ─── MANUAL TRIGGER ────────────────────────────────────────────────

    public function test_manual_trigger_without_conditions_dispatches(): void
    {
        $user = TestUser::create(['name' => 'MAN', 'email' => 'man@test.com']);

        $this->createTrigger([
            'trigger_type' => 'manual',
            'trigger_config' => [],
            'conditions' => null,
            'model_class' => TestUser::class,
        ]);

        $deliveryService = app(DeliveryService::class);
        $trigger = AutomationTrigger::active()->where('trigger_type', 'manual')->first();
        $delivery = $deliveryService->dispatchForManualTrigger($trigger, $user, userId: 1);

        $this->assertNotNull($delivery);
        $this->assertSame('manual', $delivery->payload['trigger_context']['trigger_source']);
    }

    public function test_manual_trigger_with_condition_equals_dispatches(): void
    {
        $user = TestUser::create(['name' => 'MAN-EQ', 'email' => 'maneq@test.com', 'status' => 'premium']);

        $this->createTrigger([
            'trigger_type' => 'manual',
            'trigger_config' => [],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
            'model_class' => TestUser::class,
        ]);

        $deliveryService = app(DeliveryService::class);
        $trigger = AutomationTrigger::active()->where('trigger_type', 'manual')->first();
        $delivery = $deliveryService->dispatchForManualTrigger($trigger, $user, userId: 1);

        $this->assertNotNull($delivery);
    }

    public function test_manual_trigger_with_condition_skips_when_not_matching(): void
    {
        $user = TestUser::create(['name' => 'MAN-SK', 'email' => 'mansk@test.com', 'status' => 'active']);

        $this->createTrigger([
            'trigger_type' => 'manual',
            'trigger_config' => [],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
            'model_class' => TestUser::class,
        ]);

        $deliveryService = app(DeliveryService::class);
        $trigger = AutomationTrigger::active()->where('trigger_type', 'manual')->first();
        $delivery = $deliveryService->dispatchForManualTrigger($trigger, $user, userId: 1);

        $this->assertNull($delivery);
    }

    // ─── DATE CONDITION TRIGGER ────────────────────────────────────────

    public function test_date_condition_trigger_without_conditions_dispatches(): void
    {
        $user = TestUser::create(['name' => 'DC', 'email' => 'dc@test.com']);

        $this->createTrigger([
            'trigger_type' => 'date-condition',
            'trigger_config' => [
                'date_field' => 'created_at',
                'condition_type' => 'before',
                'days' => 7,
            ],
            'conditions' => null,
            'model_class' => TestUser::class,
        ]);

        $deliveryService = app(DeliveryService::class);
        $trigger = AutomationTrigger::active()->where('trigger_type', 'date-condition')->first();
        $delivery = $deliveryService->dispatchForDateCondition($trigger, $user, [
            'date_field' => 'created_at',
            'date_value' => $user->created_at,
            'condition' => 'before',
            'days' => 7,
        ]);

        $this->assertNotNull($delivery);
    }

    public function test_date_condition_trigger_with_condition_equals_dispatches(): void
    {
        $user = TestUser::create(['name' => 'DC-EQ', 'email' => 'dceq@test.com', 'status' => 'premium']);

        $this->createTrigger([
            'trigger_type' => 'date-condition',
            'trigger_config' => [
                'date_field' => 'created_at',
                'condition_type' => 'before',
                'days' => 7,
            ],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
            'model_class' => TestUser::class,
        ]);

        $deliveryService = app(DeliveryService::class);
        $trigger = AutomationTrigger::active()->where('trigger_type', 'date-condition')->first();
        $delivery = $deliveryService->dispatchForDateCondition($trigger, $user, [
            'date_field' => 'created_at',
            'date_value' => $user->created_at,
            'condition' => 'before',
            'days' => 7,
        ]);

        $this->assertNotNull($delivery);
    }

    public function test_date_condition_trigger_with_condition_skips_when_not_matching(): void
    {
        $user = TestUser::create(['name' => 'DC-SK', 'email' => 'dcsk@test.com', 'status' => 'active']);

        $this->createTrigger([
            'trigger_type' => 'date-condition',
            'trigger_config' => [
                'date_field' => 'created_at',
                'condition_type' => 'before',
                'days' => 7,
            ],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
            'model_class' => TestUser::class,
        ]);

        $deliveryService = app(DeliveryService::class);
        $trigger = AutomationTrigger::active()->where('trigger_type', 'date-condition')->first();
        $delivery = $deliveryService->dispatchForDateCondition($trigger, $user, [
            'date_field' => 'created_at',
            'date_value' => $user->created_at,
            'condition' => 'before',
            'days' => 7,
        ]);

        $this->assertNull($delivery);
    }

    // ─── NESTED (DOT-NOTATION) FIELD CONDITIONS ────────────────────────

    public function test_nested_field_customer_status_equals_dispatches(): void
    {
        $user = TestUser::create(['name' => 'Cust', 'email' => 'cust@test.com', 'status' => 'premium']);
        $order = TestOrder::create(['user_id' => $user->id, 'total' => 99.99, 'status' => 'pending']);

        $trigger = $this->createTrigger([
            'model_class' => TestOrder::class,
            'event' => EventEnum::Created,
            'trigger_type' => 'model-event',
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'customer.status', 'operator' => 'equals', 'value' => 'premium'],
            ],
        ]);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $order, EventEnum::Created, $order->getOriginal());

        $this->assertNotNull($delivery);
    }

    public function test_nested_field_customer_status_equals_skips(): void
    {
        $user = TestUser::create(['name' => 'Cust2', 'email' => 'cust2@test.com', 'status' => 'active']);
        $order = TestOrder::create(['user_id' => $user->id, 'total' => 50.00, 'status' => 'pending']);

        $trigger = $this->createTrigger([
            'model_class' => TestOrder::class,
            'event' => EventEnum::Created,
            'trigger_type' => 'model-event',
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'customer.status', 'operator' => 'equals', 'value' => 'premium'],
            ],
        ]);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $order, EventEnum::Created, $order->getOriginal());

        $this->assertNull($delivery);
    }

    public function test_nested_field_customer_name_contains_dispatches(): void
    {
        $user = TestUser::create(['name' => 'VIP User', 'email' => 'vip@test.com']);
        $order = TestOrder::create(['user_id' => $user->id, 'total' => 199.99, 'status' => 'pending']);

        $trigger = $this->createTrigger([
            'model_class' => TestOrder::class,
            'event' => EventEnum::Created,
            'trigger_type' => 'model-event',
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'customer.name', 'operator' => 'contains', 'value' => 'VIP'],
            ],
        ]);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $order, EventEnum::Created, $order->getOriginal());

        $this->assertNotNull($delivery);
    }

    public function test_nested_field_score_greater_than_dispatches(): void
    {
        $user = TestUser::create(['name' => 'High', 'email' => 'high@test.com', 'score' => 100]);
        $order = TestOrder::create(['user_id' => $user->id, 'total' => 500.00, 'status' => 'processing']);

        $trigger = $this->createTrigger([
            'model_class' => TestOrder::class,
            'event' => EventEnum::Created,
            'trigger_type' => 'model-event',
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'customer.score', 'operator' => 'greater_than', 'value' => '50'],
            ],
        ]);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $order, EventEnum::Created, $order->getOriginal());

        $this->assertNotNull($delivery);
    }

    public function test_nested_and_or_condition_combination(): void
    {
        $user = TestUser::create(['name' => 'VIP Premium', 'email' => 'vipprem@test.com', 'status' => 'premium', 'score' => 100]);
        $order = TestOrder::create(['user_id' => $user->id, 'total' => 1000.00, 'status' => 'processing']);

        $trigger = $this->createTrigger([
            'model_class' => TestOrder::class,
            'event' => EventEnum::Created,
            'trigger_type' => 'model-event',
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'customer.status', 'operator' => 'equals', 'value' => 'premium'],
                ['field' => 'total', 'operator' => 'greater_than', 'value' => '500', 'logic' => 'AND'],
                ['field' => 'customer.score', 'operator' => 'greater_than', 'value' => '50', 'logic' => 'AND'],
            ],
        ]);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $order, EventEnum::Created, $order->getOriginal());

        $this->assertNotNull($delivery);
    }

    public function test_nested_field_boolean_is_visible_equals(): void
    {
        $user = TestUser::create(['name' => 'Visible', 'email' => 'vis@test.com', 'is_visible' => true]);
        $order = TestOrder::create(['user_id' => $user->id, 'total' => 75.00, 'status' => 'pending']);

        $trigger = $this->createTrigger([
            'model_class' => TestOrder::class,
            'event' => EventEnum::Created,
            'trigger_type' => 'model-event',
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'customer.is_visible', 'operator' => 'equals', 'value' => '1'],
            ],
        ]);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->dispatch($trigger, $order, EventEnum::Created, $order->getOriginal());

        $this->assertNotNull($delivery);
    }

    // ─── CROSS-TRIGGER-TYPE: SAME CONDITION, DIFFERENT TYPES ──────────

    public function test_same_condition_works_across_model_event_and_schedule(): void
    {
        $user = TestUser::create(['name' => 'Cross', 'email' => 'cross@test.com', 'status' => 'premium', 'score' => 100]);
        $condition = ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'];

        // model-event
        $trigger1 = $this->createTrigger([
            'name' => 'Cross ME',
            'trigger_type' => 'model-event',
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [$condition],
        ]);

        $deliveryService = app(DeliveryService::class);
        $d1 = $deliveryService->dispatch($trigger1, $user, EventEnum::Created, $user->getOriginal());
        $this->assertNotNull($d1, 'model-event with condition should dispatch');

        // schedule
        $trigger2 = $this->createTrigger([
            'name' => 'Cross SCH',
            'trigger_type' => 'schedule',
            'trigger_config' => ['schedule_type' => 'daily'],
            'conditions' => [$condition],
        ]);

        $d2 = $deliveryService->dispatchForSchedule($trigger2, $user);
        $this->assertNotNull($d2, 'schedule with condition should dispatch');

        // manual
        $trigger3 = $this->createTrigger([
            'name' => 'Cross MAN',
            'trigger_type' => 'manual',
            'trigger_config' => [],
            'conditions' => [$condition],
        ]);

        $d3 = $deliveryService->dispatchForManualTrigger($trigger3, $user, userId: 1);
        $this->assertNotNull($d3, 'manual with condition should dispatch');

        // event
        $trigger4 = $this->createTrigger([
            'name' => 'Cross EV',
            'trigger_type' => 'event',
            'trigger_config' => ['event_class' => 'cross.event'],
            'conditions' => [$condition],
        ]);

        TriggerManager::addSubscribedEvent('cross.event', $trigger4->id);
        $d4 = $deliveryService->dispatchForEventTrigger($trigger4, $user, ['event_class' => 'cross.event']);
        $this->assertNotNull($d4, 'event with condition should dispatch');

        // date-condition
        $trigger5 = $this->createTrigger([
            'name' => 'Cross DC',
            'trigger_type' => 'date-condition',
            'trigger_config' => ['date_field' => 'created_at', 'condition_type' => 'before', 'days' => 7],
            'conditions' => [$condition],
        ]);

        $d5 = $deliveryService->dispatchForDateCondition($trigger5, $user, [
            'date_field' => 'created_at',
            'date_value' => $user->created_at,
            'condition' => 'before',
            'days' => 7,
        ]);
        $this->assertNotNull($d5, 'date-condition with condition should dispatch');
    }
}
