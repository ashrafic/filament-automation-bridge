<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Unit\Conditions;

use Ashrafic\FilamentWebhookBridge\Conditions\ConditionRegistry;
use Ashrafic\FilamentWebhookBridge\Conditions\Operators\ContainsOperator;
use Ashrafic\FilamentWebhookBridge\Conditions\Operators\EqualsOperator;
use Ashrafic\FilamentWebhookBridge\Contracts\ConditionOperator;
use PHPUnit\Framework\TestCase;

class ConditionRegistryTest extends TestCase
{
    protected ConditionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ConditionRegistry;
    }

    public function test_registers_all_operators(): void
    {
        $all = $this->registry->all();

        $this->assertCount(9, $all);
        $this->assertArrayHasKey('equals', $all);
        $this->assertArrayHasKey('not_equals', $all);
        $this->assertArrayHasKey('contains', $all);
        $this->assertArrayHasKey('greater_than', $all);
        $this->assertArrayHasKey('less_than', $all);
        $this->assertArrayHasKey('is_empty', $all);
        $this->assertArrayHasKey('is_not_empty', $all);
        $this->assertArrayHasKey('changed', $all);
        $this->assertArrayHasKey('changed_to', $all);
    }

    public function test_retrieves_operator_by_key(): void
    {
        $equals = $this->registry->get('equals');
        $this->assertInstanceOf(EqualsOperator::class, $equals);
        $this->assertInstanceOf(ConditionOperator::class, $equals);

        $contains = $this->registry->get('contains');
        $this->assertInstanceOf(ContainsOperator::class, $contains);
    }

    public function test_throws_for_unknown_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Condition operator [unknown] not found.');

        $this->registry->get('unknown');
    }

    public function test_for_created_event_excludes_changed_operators(): void
    {
        $created = $this->registry->forCreatedEvent();

        $this->assertArrayNotHasKey('changed', $created);
        $this->assertArrayNotHasKey('changed_to', $created);
        $this->assertArrayHasKey('equals', $created);
        $this->assertArrayHasKey('not_equals', $created);
        $this->assertArrayHasKey('contains', $created);
        $this->assertCount(7, $created);
    }

    public function test_for_updated_event_includes_all_operators(): void
    {
        $updated = $this->registry->forUpdatedEvent();

        $this->assertArrayHasKey('changed', $updated);
        $this->assertArrayHasKey('changed_to', $updated);
        $this->assertArrayHasKey('equals', $updated);
        $this->assertCount(9, $updated);
    }
}
