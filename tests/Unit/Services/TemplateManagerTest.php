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

    public function test_get_all_returns_templates_sorted_by_name(): void
    {
        AutomationTemplate::create([
            'name' => 'Z Template',
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Custom,
            'field_mapping' => ['name'],
            'payload_mode' => PayloadMode::Summary,
        ]);

        AutomationTemplate::create([
            'name' => 'A Template',
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Updated,
            'destination_type' => DestinationType::Custom,
            'field_mapping' => ['email'],
            'payload_mode' => PayloadMode::Summary,
        ]);

        $all = $this->manager->getAll();

        $this->assertSame('A Template', $all->first()->name);
        $this->assertSame('Z Template', $all->last()->name);
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
        $this->assertSame('My Saved Template', $template->name);
        $this->assertSame('A description', $template->description);
        $this->assertSame('App\\Models\\User', $template->model_class);
        $this->assertSame(EventEnum::Created, $template->event);
        $this->assertSame(DestinationType::Zapier, $template->destination_type);
        $this->assertSame(['name', 'email'], $template->field_mapping);
        $this->assertSame(PayloadMode::Summary, $template->payload_mode);
    }

    public function test_save_from_trigger_preserves_conditions(): void
    {
        $trigger = AutomationTrigger::create([
            'name' => 'Conditional Trigger',
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Updated,
            'destination_type' => DestinationType::Custom,
            'destination_url' => 'https://example.com/hook',
            'field_mapping' => ['name'],
            'payload_mode' => PayloadMode::Summary,
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'premium'],
            ],
            'active' => true,
            'max_retries' => 3,
            'request_timeout' => 5,
        ]);

        $template = $this->manager->saveFromTrigger($trigger, 'Conditional Template');

        $this->assertNotNull($template->conditions);
        $this->assertSame('status', $template->conditions[0]['field']);
    }

    public function test_apply_template_returns_fillable_array(): void
    {
        $template = AutomationTemplate::create([
            'name' => 'Apply Test',
            'model_class' => 'App\\Models\\Order',
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Zapier,
            'field_mapping' => ['id', 'total'],
            'payload_mode' => PayloadMode::All,
        ]);

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

    public function test_apply_template_includes_custom_payload(): void
    {
        $template = AutomationTemplate::create([
            'name' => 'Custom Payload Template',
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Custom,
            'field_mapping' => [],
            'payload_mode' => PayloadMode::Custom,
            'custom_payload_template' => '{"name": "{{ name }}"}',
        ]);

        $result = $this->manager->applyTemplate($template);

        $this->assertSame('{"name": "{{ name }}"}', $result['custom_payload_template']);
    }
}
