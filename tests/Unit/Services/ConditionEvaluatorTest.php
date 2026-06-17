<?php

namespace Ashrafic\FilamentAutomationBridge\Tests\Unit\Services;

use Ashrafic\FilamentAutomationBridge\Conditions\ConditionRegistry;
use Ashrafic\FilamentAutomationBridge\Services\ConditionEvaluator;
use Ashrafic\FilamentAutomationBridge\Tests\Fixtures\Models\TestLead;
use Ashrafic\FilamentAutomationBridge\Tests\Fixtures\Models\TestOrder;
use Ashrafic\FilamentAutomationBridge\Tests\Fixtures\Models\TestUser;
use Ashrafic\FilamentAutomationBridge\Tests\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    protected ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ConditionEvaluator(new ConditionRegistry);
    }

    protected function createUser(array $attributes = []): TestUser
    {
        return TestUser::create(array_merge([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ], $attributes));
    }

    public function test_evaluate_returns_true_when_conditions_are_null(): void
    {
        $user = $this->createUser();

        $this->assertTrue($this->evaluator->evaluate($user, null));
    }

    public function test_evaluate_returns_true_when_conditions_are_empty(): void
    {
        $user = $this->createUser();

        $this->assertTrue($this->evaluator->evaluate($user, []));
    }

    public function test_equals_operator_matches(): void
    {
        $user = $this->createUser(['status' => 'active']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
        ]);

        $this->assertTrue($result);
    }

    public function test_equals_operator_does_not_match(): void
    {
        $user = $this->createUser(['status' => 'active']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'inactive'],
        ]);

        $this->assertFalse($result);
    }

    public function test_not_equals_operator(): void
    {
        $user = $this->createUser(['status' => 'active']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'status', 'operator' => 'not_equals', 'value' => 'inactive'],
        ]);

        $this->assertTrue($result);
    }

    public function test_contains_operator_with_string(): void
    {
        $user = $this->createUser(['name' => 'John Doe']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'name', 'operator' => 'contains', 'value' => 'Doe'],
        ]);

        $this->assertTrue($result);
    }

    public function test_greater_than_operator(): void
    {
        $user = $this->createUser();

        $order = TestOrder::create([
            'user_id' => $user->id,
            'total' => 100.50,
            'status' => 'pending',
        ]);

        $result = $this->evaluator->evaluate($order, [
            ['field' => 'total', 'operator' => 'greater_than', 'value' => '50'],
        ]);

        $this->assertTrue($result);
    }

    public function test_less_than_operator(): void
    {
        $user = $this->createUser();

        $order = TestOrder::create([
            'user_id' => $user->id,
            'total' => 25.00,
            'status' => 'pending',
        ]);

        $result = $this->evaluator->evaluate($order, [
            ['field' => 'total', 'operator' => 'less_than', 'value' => '50'],
        ]);

        $this->assertTrue($result);
    }

    public function test_is_empty_operator_with_null(): void
    {
        $user = $this->createUser();
        $lead = TestLead::create([
            'name' => 'Lead',
            'email' => 'lead@example.com',
            'phone' => null,
        ]);

        $result = $this->evaluator->evaluate($lead, [
            ['field' => 'phone', 'operator' => 'is_empty'],
        ]);

        $this->assertTrue($result);
    }

    public function test_is_not_empty_operator(): void
    {
        $user = $this->createUser(['name' => 'John']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'name', 'operator' => 'is_not_empty'],
        ]);

        $this->assertTrue($result);
    }

    public function test_changed_operator_with_original(): void
    {
        $user = $this->createUser(['status' => 'inactive']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'status', 'operator' => 'changed', 'logic' => 'AND'],
        ], ['status' => 'active']);

        $this->assertTrue($result);
    }

    public function test_changed_operator_same_value_returns_false(): void
    {
        $user = $this->createUser(['status' => 'active']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'status', 'operator' => 'changed'],
        ], ['status' => 'active']);

        $this->assertFalse($result);
    }

    public function test_changed_to_operator(): void
    {
        $user = $this->createUser(['status' => 'premium']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'status', 'operator' => 'changed_to', 'value' => 'premium'],
        ], ['status' => 'active']);

        $this->assertTrue($result);
    }

    public function test_changed_to_operator_same_value_returns_false(): void
    {
        $user = $this->createUser(['status' => 'active']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'status', 'operator' => 'changed_to', 'value' => 'active'],
        ], ['status' => 'active']);

        $this->assertFalse($result);
    }

    public function test_and_logic_all_must_match(): void
    {
        $user = $this->createUser(['name' => 'John', 'status' => 'active']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'name', 'operator' => 'equals', 'value' => 'John', 'logic' => 'AND'],
            ['field' => 'status', 'operator' => 'equals', 'value' => 'active', 'logic' => 'AND'],
        ]);

        $this->assertTrue($result);
    }

    public function test_and_logic_fails_if_one_does_not_match(): void
    {
        $user = $this->createUser(['name' => 'John', 'status' => 'inactive']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'name', 'operator' => 'equals', 'value' => 'John', 'logic' => 'AND'],
            ['field' => 'status', 'operator' => 'equals', 'value' => 'active', 'logic' => 'AND'],
        ]);

        $this->assertFalse($result);
    }

    public function test_or_logic_passes_if_any_matches(): void
    {
        $user = $this->createUser(['status' => 'active']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'active', 'logic' => 'AND'],
            ['field' => 'status', 'operator' => 'equals', 'value' => 'inactive', 'logic' => 'OR'],
        ]);

        $this->assertTrue($result);
    }

    public function test_or_logic_fails_if_none_match(): void
    {
        $user = $this->createUser(['status' => 'pending']);

        $result = $this->evaluator->evaluate($user, [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'active', 'logic' => 'AND'],
            ['field' => 'status', 'operator' => 'equals', 'value' => 'inactive', 'logic' => 'OR'],
        ]);

        $this->assertFalse($result);
    }
}
