<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Unit\Services;

use Ashrafic\FilamentWebhookBridge\Services\FieldSchemaAnalyzer;
use Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models\TestOrder;
use Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models\TestUser;
use Ashrafic\FilamentWebhookBridge\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class FieldSchemaAnalyzerTest extends TestCase
{
    protected FieldSchemaAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = $this->app->make(FieldSchemaAnalyzer::class);
        Cache::flush();
    }

    public function test_get_attribute_names_for_model(): void
    {
        $attributes = $this->analyzer->getAttributeNames(TestOrder::class);

        $names = array_map(fn (array $attr) => $attr['name'], $attributes);

        $this->assertContains('id', $names);
        $this->assertContains('total', $names);
        $this->assertContains('status', $names);
        $this->assertContains('paid_at', $names);
        $this->assertContains('user_id', $names);
    }

    public function test_get_attribute_names_excludes_configured_fields(): void
    {
        $attributes = $this->analyzer->getAttributeNames(TestUser::class);

        $names = array_map(fn (array $attr) => $attr['name'], $attributes);

        $this->assertNotContains('password', $names);
        $this->assertNotContains('remember_token', $names);
    }

    public function test_get_attribute_names_returns_empty_for_invalid_class(): void
    {
        $attributes = $this->analyzer->getAttributeNames('App\\Models\\NonExistent');

        $this->assertEmpty($attributes);
    }

    public function test_attribute_entries_have_computed_flag(): void
    {
        $attributes = $this->analyzer->getAttributeNames(TestOrder::class);

        foreach ($attributes as $attr) {
            $this->assertArrayHasKey('name', $attr);
            $this->assertArrayHasKey('computed', $attr);
            $this->assertIsBool($attr['computed']);
        }
    }

    public function test_get_relations_for_model(): void
    {
        $relations = $this->analyzer->getRelations(TestOrder::class);

        $relationNames = array_map(fn (array $r) => $r['name'], $relations);

        $this->assertContains('customer', $relationNames);
        $this->assertContains('items', $relationNames);
    }

    public function test_relations_include_type(): void
    {
        $relations = $this->analyzer->getRelations(TestOrder::class);

        $customer = collect($relations)->firstWhere('name', 'customer');
        $items = collect($relations)->firstWhere('name', 'items');

        $this->assertSame('BelongsTo', $customer['type']);
        $this->assertSame('HasMany', $items['type']);
    }

    public function test_relations_include_model_class(): void
    {
        $relations = $this->analyzer->getRelations(TestOrder::class);

        $customer = collect($relations)->firstWhere('name', 'customer');
        $items = collect($relations)->firstWhere('name', 'items');

        $this->assertSame(TestUser::class, $customer['model']);
    }

    public function test_get_relations_returns_empty_for_invalid_class(): void
    {
        $relations = $this->analyzer->getRelations('App\\Models\\NonExistent');

        $this->assertEmpty($relations);
    }

    public function test_validate_valid_field_path(): void
    {
        $this->assertTrue($this->analyzer->validateFieldPath(TestOrder::class, 'customer.email'));
    }

    public function test_validate_simple_attribute_field_path(): void
    {
        $this->assertTrue($this->analyzer->validateFieldPath(TestOrder::class, 'total'));
    }

    public function test_reject_invalid_field_path(): void
    {
        $this->assertFalse($this->analyzer->validateFieldPath(TestOrder::class, 'nonexistent.field'));
    }

    public function test_reject_invalid_relation_in_field_path(): void
    {
        $this->assertFalse($this->analyzer->validateFieldPath(TestOrder::class, 'invalid_relation.name'));
    }

    public function test_reject_empty_field_path(): void
    {
        $this->assertFalse($this->analyzer->validateFieldPath(TestOrder::class, ''));
    }

    public function test_analyze_returns_schema_structure(): void
    {
        $schema = $this->analyzer->analyze(TestOrder::class);

        $this->assertArrayHasKey('label', $schema);
        $this->assertArrayHasKey('model', $schema);
        $this->assertArrayHasKey('attributes', $schema);
        $this->assertArrayHasKey('relations', $schema);
        $this->assertSame('TestOrder', $schema['label']);
        $this->assertSame(TestOrder::class, $schema['model']);
    }
}
