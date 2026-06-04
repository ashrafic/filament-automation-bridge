<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Unit\Models;

use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Enums\PayloadMode;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTemplate;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Tests\TestCase;

class WebhookTemplateTest extends TestCase
{
    protected function createTemplate(array $overrides = []): WebhookTemplate
    {
        return WebhookTemplate::create(array_merge([
            'name' => 'User Created Template',
            'model_class' => 'App\\Models\\User',
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Zapier,
            'field_mapping' => ['name', 'email'],
            'payload_mode' => PayloadMode::Summary,
        ], $overrides));
    }

    public function test_to_trigger_converts_template_to_trigger_with_defaults(): void
    {
        $template = $this->createTemplate();

        $trigger = $template->toTrigger();

        $this->assertInstanceOf(WebhookTrigger::class, $trigger);
        $this->assertSame('User Created Template', $trigger->name);
        $this->assertSame('App\\Models\\User', $trigger->model_class);
        $this->assertSame(EventEnum::Created, $trigger->event);
        $this->assertSame(DestinationType::Zapier, $trigger->destination_type);
        $this->assertSame(['name', 'email'], $trigger->field_mapping);
        $this->assertSame(PayloadMode::Summary, $trigger->payload_mode);
    }

    public function test_to_trigger_creates_inactive_trigger_by_default(): void
    {
        $template = $this->createTemplate();

        $trigger = $template->toTrigger();

        $this->assertNull($trigger->active);
    }

    public function test_to_trigger_merges_override_values(): void
    {
        $template = $this->createTemplate();

        $trigger = $template->toTrigger([
            'name' => 'Overridden Name',
            'destination_url' => 'https://new.example.com/hook',
            'active' => true,
        ]);

        $this->assertSame('Overridden Name', $trigger->name);
        $this->assertSame('https://new.example.com/hook', $trigger->destination_url);
        $this->assertTrue($trigger->active);
        $this->assertSame('App\\Models\\User', $trigger->model_class);
        $this->assertSame(EventEnum::Created, $trigger->event);
    }
}
