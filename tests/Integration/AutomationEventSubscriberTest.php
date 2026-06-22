<?php

namespace Ashrafic\FilamentAutomationBridge\Tests\Integration;

use Ashrafic\FilamentAutomationBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Tests\Fixtures\Models\TestUser;
use Ashrafic\FilamentAutomationBridge\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

class AutomationEventSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filament-automation-bridge.sandbox_mode', true);
    }

    protected function createTrigger(array $overrides = []): AutomationTrigger
    {
        return AutomationTrigger::create(array_merge([
            'name' => 'Pipeline Test Trigger',
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

    protected function assertDeliveryCreated(string $event): AutomationDelivery
    {
        $delivery = AutomationDelivery::where('source', 'realtime')->latest()->first();

        $this->assertNotNull($delivery, "Expected delivery to be created for {$event} event");
        $this->assertSame(DeliveryStatus::Success, $delivery->status, 'Delivery should be marked success in sandbox mode');

        return $delivery;
    }

    protected function assertNoDeliveryCreated(): void
    {
        $delivery = AutomationDelivery::where('source', 'realtime')->latest()->first();

        $this->assertNull($delivery, 'Expected NO delivery to be created');
    }

    public function test_created_event_without_conditions_dispatches(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => null,
        ]);

        TestUser::create(['name' => 'Pipeline Test', 'email' => 'pipeline@test.com']);

        $this->assertDeliveryCreated('created');
    }

    public function test_updated_event_without_conditions_dispatches(): void
    {
        $user = TestUser::create(['name' => 'Original', 'email' => 'original@test.com']);

        $this->createTrigger([
            'event' => EventEnum::Updated,
            'trigger_config' => ['event' => 'updated', 'watch_fields' => []],
            'conditions' => null,
        ]);

        $user->update(['name' => 'Updated Name']);

        $this->assertDeliveryCreated('updated');
    }

    public function test_deleted_event_without_conditions_dispatches(): void
    {
        $user = TestUser::create(['name' => 'To Delete', 'email' => 'delete@test.com']);

        $this->createTrigger([
            'event' => EventEnum::Deleted,
            'trigger_config' => ['event' => 'deleted', 'watch_fields' => []],
            'conditions' => null,
        ]);

        $user->delete();

        $this->assertDeliveryCreated('deleted');
    }

    public function test_empty_conditions_array_dispatches(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [],
        ]);

        TestUser::create(['name' => 'EmptyCond', 'email' => 'empty@test.com']);

        $this->assertDeliveryCreated('created');
    }

    public function test_equals_operator_matching_condition_dispatches(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
        ]);

        TestUser::create(['name' => 'Premium', 'email' => 'premium@test.com', 'status' => 'premium']);

        $this->assertDeliveryCreated('created');
    }

    public function test_equals_operator_non_matching_condition_skips(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
        ]);

        TestUser::create(['name' => 'Regular', 'email' => 'regular@test.com', 'status' => 'active']);

        $this->assertNoDeliveryCreated();
    }

    public function test_not_equals_operator_dispatches(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'not_equals', 'value' => 'banned'],
            ],
        ]);

        TestUser::create(['name' => 'Active', 'email' => 'active@test.com', 'status' => 'active']);

        $this->assertDeliveryCreated('created');
    }

    public function test_not_equals_operator_skips_when_matches_value(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'not_equals', 'value' => 'banned'],
            ],
        ]);

        TestUser::create(['name' => 'Banned', 'email' => 'banned@test.com', 'status' => 'banned']);

        $this->assertNoDeliveryCreated();
    }

    public function test_contains_operator_dispatches(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'name', 'operator' => 'contains', 'value' => 'Doe'],
            ],
        ]);

        TestUser::create(['name' => 'John Doe', 'email' => 'john@test.com']);

        $this->assertDeliveryCreated('created');
    }

    public function test_greater_than_operator_dispatches(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'score', 'operator' => 'greater_than', 'value' => '50'],
            ],
        ]);

        TestUser::create(['name' => 'High Score', 'email' => 'high@test.com', 'score' => 100]);

        $this->assertDeliveryCreated('created');
    }

    public function test_greater_than_operator_skips_below_threshold(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'score', 'operator' => 'greater_than', 'value' => '50'],
            ],
        ]);

        TestUser::create(['name' => 'Low Score', 'email' => 'low@test.com', 'score' => 25]);

        $this->assertNoDeliveryCreated();
    }

    public function test_less_than_operator_dispatches(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'score', 'operator' => 'less_than', 'value' => '50'],
            ],
        ]);

        TestUser::create(['name' => 'Low Score', 'email' => 'low@test.com', 'score' => 25]);

        $this->assertDeliveryCreated('created');
    }

    public function test_is_empty_operator_dispatches_for_null(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'score', 'operator' => 'is_empty'],
            ],
        ]);

        TestUser::create(['name' => 'No Score', 'email' => 'noscore@test.com', 'score' => null]);

        $this->assertDeliveryCreated('created');
    }

    public function test_is_not_empty_operator_dispatches(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'name', 'operator' => 'is_not_empty'],
            ],
        ]);

        TestUser::create(['name' => 'Has Name', 'email' => 'hasname@test.com']);

        $this->assertDeliveryCreated('created');
    }

    public function test_is_not_empty_operator_skips_for_null(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'score', 'operator' => 'is_not_empty'],
            ],
        ]);

        TestUser::create(['name' => 'Null Score', 'email' => 'null@test.com', 'score' => null]);

        $this->assertNoDeliveryCreated();
    }

    public function test_boolean_field_equals_one_dispatches(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'is_visible', 'operator' => 'equals', 'value' => '1'],
            ],
        ]);

        TestUser::create(['name' => 'Visible', 'email' => 'visible@test.com', 'is_visible' => true]);

        $this->assertDeliveryCreated('created');
    }

    public function test_boolean_field_equals_zero_dispatches(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'is_visible', 'operator' => 'equals', 'value' => '0'],
            ],
        ]);

        TestUser::create(['name' => 'Hidden', 'email' => 'hidden@test.com', 'is_visible' => false]);

        $this->assertDeliveryCreated('created');
    }

    public function test_boolean_field_equals_one_skips_when_false(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'is_visible', 'operator' => 'equals', 'value' => '1'],
            ],
        ]);

        TestUser::create(['name' => 'Hidden', 'email' => 'hidden@test.com', 'is_visible' => false]);

        $this->assertNoDeliveryCreated();
    }

    public function test_and_logic_all_must_match_to_dispatch(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium', 'logic' => 'AND'],
                ['field' => 'score', 'operator' => 'greater_than', 'value' => '50', 'logic' => 'AND'],
            ],
        ]);

        TestUser::create(['name' => 'Premium High', 'email' => 'ph@test.com', 'status' => 'premium', 'score' => 100]);

        $this->assertDeliveryCreated('created');
    }

    public function test_and_logic_fails_if_any_does_not_match(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium', 'logic' => 'AND'],
                ['field' => 'score', 'operator' => 'greater_than', 'value' => '50', 'logic' => 'AND'],
            ],
        ]);

        TestUser::create(['name' => 'Premium Low', 'email' => 'pl@test.com', 'status' => 'premium', 'score' => 25]);

        $this->assertNoDeliveryCreated();
    }

    public function test_or_logic_dispatches_if_any_group_matches(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
                ['field' => 'score', 'operator' => 'greater_than', 'value' => '50', 'logic' => 'OR'],
            ],
        ]);

        TestUser::create(['name' => 'Basic High', 'email' => 'bh@test.com', 'status' => 'active', 'score' => 100]);

        $this->assertDeliveryCreated('created');
    }

    public function test_changed_operator_on_update_dispatches(): void
    {
        $user = TestUser::create(['name' => 'Before', 'email' => 'before@test.com', 'status' => 'active']);

        $this->createTrigger([
            'event' => EventEnum::Updated,
            'trigger_config' => ['event' => 'updated', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'changed'],
            ],
        ]);

        $user->update(['status' => 'premium']);

        $this->assertDeliveryCreated('updated');
    }

    public function test_changed_operator_skips_when_value_same(): void
    {
        $user = TestUser::create(['name' => 'Same', 'email' => 'same@test.com', 'status' => 'active']);

        $this->createTrigger([
            'event' => EventEnum::Updated,
            'trigger_config' => ['event' => 'updated', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'changed'],
            ],
        ]);

        $user->update(['name' => 'Same Name']);

        $this->assertNoDeliveryCreated();
    }

    public function test_changed_to_operator_dispatches(): void
    {
        $user = TestUser::create(['name' => 'Before', 'email' => 'before@test.com', 'status' => 'active']);

        $this->createTrigger([
            'event' => EventEnum::Updated,
            'trigger_config' => ['event' => 'updated', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'changed_to', 'value' => 'premium'],
            ],
        ]);

        $user->update(['status' => 'premium']);

        $this->assertDeliveryCreated('updated');
    }

    public function test_changed_to_operator_skips_when_already_that_value(): void
    {
        $user = TestUser::create(['name' => 'Already', 'email' => 'already@test.com', 'status' => 'premium']);

        $this->createTrigger([
            'event' => EventEnum::Updated,
            'trigger_config' => ['event' => 'updated', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'changed_to', 'value' => 'premium'],
            ],
        ]);

        $user->update(['name' => 'Updated Name']);

        $this->assertNoDeliveryCreated();
    }

    public function test_boolean_changed_on_update_dispatches(): void
    {
        $user = TestUser::create(['name' => 'Vis', 'email' => 'vis@test.com', 'is_visible' => true]);

        $this->createTrigger([
            'event' => EventEnum::Updated,
            'trigger_config' => ['event' => 'updated', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'is_visible', 'operator' => 'changed'],
            ],
        ]);

        $user->update(['is_visible' => false]);

        $this->assertDeliveryCreated('updated');
    }

    public function test_multiple_conditions_all_must_match(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
                ['field' => 'is_visible', 'operator' => 'equals', 'value' => '1'],
                ['field' => 'score', 'operator' => 'greater_than', 'value' => '50'],
            ],
        ]);

        TestUser::create([
            'name' => 'All Match',
            'email' => 'all@test.com',
            'status' => 'premium',
            'is_visible' => true,
            'score' => 100,
        ]);

        $this->assertDeliveryCreated('created');
    }

    public function test_active_false_triggers_are_skipped(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'active' => false,
            'conditions' => null,
        ]);

        TestUser::create(['name' => 'Inactive Trigger', 'email' => 'inactive@test.com']);

        $this->assertNoDeliveryCreated();
    }

    public function test_different_model_class_is_skipped(): void
    {
        $this->createTrigger([
            'model_class' => 'App\\Models\\NonExistent',
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => null,
        ]);

        TestUser::create(['name' => 'Wrong Model', 'email' => 'wrong@test.com']);

        $this->assertNoDeliveryCreated();
    }

    public function test_different_event_type_is_skipped(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Deleted,
            'trigger_config' => ['event' => 'deleted', 'watch_fields' => []],
            'conditions' => null,
        ]);

        TestUser::create(['name' => 'Wrong Event', 'email' => 'wrong@test.com']);

        $this->assertNoDeliveryCreated();
    }

    public function test_created_event_with_equals_condition_true_literal(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'is_visible', 'operator' => 'equals', 'value' => 'true'],
            ],
        ]);

        TestUser::create(['name' => 'True Literal', 'email' => 'true@test.com', 'is_visible' => true]);

        $this->assertDeliveryCreated('created');
    }

    public function test_created_event_with_equals_condition_false_literal(): void
    {
        $this->createTrigger([
            'event' => EventEnum::Created,
            'trigger_config' => ['event' => 'created', 'watch_fields' => []],
            'conditions' => [
                ['field' => 'is_visible', 'operator' => 'equals', 'value' => 'false'],
            ],
        ]);

        TestUser::create(['name' => 'False Lit', 'email' => 'false@test.com', 'is_visible' => false]);

        $this->assertDeliveryCreated('created');
    }

    public function test_trigger_with_restored_event_dispatches(): void
    {
        $user = TestUser::create(['name' => 'Soft Del', 'email' => 'soft@test.com']);
        $user->delete();

        $this->createTrigger([
            'event' => EventEnum::Restored,
            'trigger_config' => ['event' => 'restored', 'watch_fields' => []],
            'conditions' => null,
        ]);

        $user->restore();

        $this->assertDeliveryCreated('restored');
    }
}
