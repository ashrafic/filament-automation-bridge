<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Feature;

use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Enums\PayloadMode;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models\TestUser;
use Illuminate\Database\QueryException;

class WebhookTriggerResourceTest extends FilamentTestCase
{
    protected function createTrigger(array $overrides = []): WebhookTrigger
    {
        return WebhookTrigger::create(array_merge([
            'name' => 'Test Trigger',
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Custom,
            'destination_url' => 'https://example.com/webhook',
            'field_mapping' => ['name', 'email'],
            'payload_mode' => PayloadMode::Summary,
            'active' => true,
        ], $overrides));
    }

    public function test_it_creates_a_trigger(): void
    {
        $trigger = $this->createTrigger([
            'name' => 'New Trigger',
            'destination_url' => 'https://example.com/hook',
        ]);

        $this->assertDatabaseHas('webhook_triggers', [
            'id' => $trigger->id,
            'name' => 'New Trigger',
            'model_class' => TestUser::class,
            'event' => EventEnum::Created->value,
            'destination_type' => DestinationType::Custom->value,
            'destination_url' => 'https://example.com/hook',
            'active' => true,
        ]);
    }

    public function test_it_validates_required_fields(): void
    {
        $this->expectException(QueryException::class);

        WebhookTrigger::create([
            'description' => 'Missing required fields',
        ]);
    }

    public function test_it_updates_a_trigger(): void
    {
        $trigger = $this->createTrigger();

        $trigger->update([
            'name' => 'Updated Trigger',
            'destination_url' => 'https://updated.example.com/webhook',
        ]);

        $this->assertDatabaseHas('webhook_triggers', [
            'id' => $trigger->id,
            'name' => 'Updated Trigger',
            'destination_url' => 'https://updated.example.com/webhook',
        ]);
    }

    public function test_it_deletes_a_trigger(): void
    {
        $trigger = $this->createTrigger();
        $triggerId = $trigger->id;

        $trigger->delete();

        $this->assertDatabaseMissing('webhook_triggers', [
            'id' => $triggerId,
        ]);
    }

    public function test_it_toggles_trigger_active_status(): void
    {
        $trigger = $this->createTrigger(['active' => true]);

        $trigger->update(['active' => false]);

        $this->assertDatabaseHas('webhook_triggers', [
            'id' => $trigger->id,
            'active' => false,
        ]);

        $trigger->update(['active' => true]);

        $this->assertDatabaseHas('webhook_triggers', [
            'id' => $trigger->id,
            'active' => true,
        ]);
    }

    public function test_it_duplicates_a_trigger(): void
    {
        $trigger = $this->createTrigger(['name' => 'Original Trigger']);

        $replica = $trigger->replicate();
        $replica->name = $trigger->name.' (Copy)';
        $replica->active = false;
        $replica->secret = WebhookTrigger::generateSecret();
        $replica->save();

        $this->assertDatabaseHas('webhook_triggers', [
            'name' => 'Original Trigger (Copy)',
            'active' => false,
        ]);

        $this->assertDatabaseHas('webhook_triggers', [
            'id' => $trigger->id,
            'name' => 'Original Trigger',
        ]);

        $this->assertCount(2, WebhookTrigger::all());
    }

    public function test_it_auto_generates_secret_when_blank(): void
    {
        $trigger = $this->createTrigger(['secret' => null]);

        $autoSecret = WebhookTrigger::generateSecret();
        $trigger->update(['secret' => $autoSecret]);

        $this->assertNotNull($trigger->fresh()->secret);
        $this->assertEquals(64, strlen($autoSecret));
    }

    public function test_it_preserves_user_provided_secret(): void
    {
        $userSecret = 'my-custom-secret-key';

        $trigger = $this->createTrigger(['secret' => $userSecret]);

        $fresh = $trigger->fresh();
        $this->assertEquals($userSecret, $fresh->secret);
    }
}
