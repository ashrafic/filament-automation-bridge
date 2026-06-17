<?php

namespace Ashrafic\FilamentAutomationBridge\Tests\Unit\Services;

use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTemplate;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\TemplateManager;
use Ashrafic\FilamentAutomationBridge\Tests\TestCase;

class TemplateManagerTest extends TestCase
{
    protected TemplateManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->app->make(TemplateManager::class);
    }

    public function test_seed_builtins_creates_templates(): void
    {
        $this->manager->seedBuiltins();

        $this->assertGreaterThanOrEqual(6, AutomationTemplate::where('is_builtin', true)->count());
    }

    public function test_seeds_builtins_idempotently(): void
    {
        $this->manager->seedBuiltins();
        $count1 = AutomationTemplate::where('is_builtin', true)->count();

        $this->manager->seedBuiltins();
        $count2 = AutomationTemplate::where('is_builtin', true)->count();

        $this->assertSame($count1, $count2);
    }

    public function test_get_all_returns_builtin_templates_first(): void
    {
        $this->manager->seedBuiltins();

        AutomationTemplate::create([
            'name' => 'Custom Template',
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Custom,
            'field_mapping' => ['name'],
            'payload_mode' => PayloadMode::Summary,
            'is_builtin' => false,
        ]);

        $all = $this->manager->getAll();

        $this->assertTrue($all->first()->is_builtin);
    }

    public function test_get_all_returns_sorted_by_name(): void
    {
        $this->manager->seedBuiltins();

        $all = $this->manager->getAll();
        $builtinNames = $all->where('is_builtin', true)->pluck('name')->values();

        $sorted = $builtinNames->sort()->values();
        $this->assertSame($sorted->toArray(), $builtinNames->toArray());
    }

    public function test_get_builtins_returns_only_builtin_templates(): void
    {
        $this->manager->seedBuiltins();

        AutomationTemplate::create([
            'name' => 'Custom Template',
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Custom,
            'field_mapping' => ['name'],
            'payload_mode' => PayloadMode::Summary,
            'is_builtin' => false,
        ]);

        $builtins = $this->manager->getBuiltins();

        $this->assertTrue($builtins->every(fn ($t) => $t->is_builtin === true));
    }

    public function test_save_from_trigger_creates_template(): void
    {
        $trigger = AutomationTrigger::create([
            'name' => 'Test Trigger',
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Zapier,
            'destination_url' => 'https://example.com/hook',
            'field_mapping' => ['name', 'email'],
            'payload_mode' => PayloadMode::Summary,
            'active' => true,
            'max_retries' => 3,
            'request_timeout' => 5,
        ]);

        $template = $this->manager->saveFromTrigger($trigger, 'My Saved Template', 'A description');

        $this->assertInstanceOf(AutomationTemplate::class, $template);
        $this->assertFalse($template->is_builtin);
        $this->assertSame('My Saved Template', $template->name);
        $this->assertSame('A description', $template->description);
        $this->assertSame('App\\Models\\User', $template->model_class);
        $this->assertSame(EventEnum::Created, $template->event);
        $this->assertSame(DestinationType::Zapier, $template->destination_type);
        $this->assertSame(['name', 'email'], $template->field_mapping);
        $this->assertSame(PayloadMode::Summary, $template->payload_mode);
    }

    public function test_apply_template_returns_fillable_array(): void
    {
        $this->manager->seedBuiltins();

        $template = AutomationTemplate::where('is_builtin', true)->first();
        $result = $this->manager->applyTemplate($template);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('model_class', $result);
        $this->assertArrayHasKey('event', $result);
        $this->assertArrayHasKey('destination_type', $result);
        $this->assertArrayHasKey('field_mapping', $result);
        $this->assertArrayHasKey('payload_mode', $result);
        $this->assertIsString($result['event']);
        $this->assertIsString($result['destination_type']);
        $this->assertIsString($result['payload_mode']);
    }
}
