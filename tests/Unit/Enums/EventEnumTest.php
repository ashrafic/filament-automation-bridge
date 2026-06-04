<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Unit\Enums;

use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use PHPUnit\Framework\TestCase;

class EventEnumTest extends TestCase
{
    public function test_has_correct_string_values(): void
    {
        $this->assertSame('created', EventEnum::Created->value);
        $this->assertSame('updated', EventEnum::Updated->value);
        $this->assertSame('deleted', EventEnum::Deleted->value);
        $this->assertSame('restored', EventEnum::Restored->value);
        $this->assertSame('force_deleted', EventEnum::ForceDeleted->value);
    }

    public function test_maps_to_eloquent_event_names(): void
    {
        $this->assertSame('created', EventEnum::Created->eloquentEvent());
        $this->assertSame('updated', EventEnum::Updated->eloquentEvent());
        $this->assertSame('deleted', EventEnum::Deleted->eloquentEvent());
        $this->assertSame('restored', EventEnum::Restored->eloquentEvent());
        $this->assertSame('forceDeleted', EventEnum::ForceDeleted->eloquentEvent());
    }

    public function test_can_be_created_from_string_via_tryFrom(): void
    {
        $this->assertSame(EventEnum::Created, EventEnum::tryFrom('created'));
        $this->assertSame(EventEnum::Updated, EventEnum::tryFrom('updated'));
        $this->assertSame(EventEnum::Deleted, EventEnum::tryFrom('deleted'));
        $this->assertSame(EventEnum::Restored, EventEnum::tryFrom('restored'));
        $this->assertSame(EventEnum::ForceDeleted, EventEnum::tryFrom('force_deleted'));
        $this->assertNull(EventEnum::tryFrom('nonexistent'));
    }

    public function test_get_label_returns_human_readable(): void
    {
        $this->assertSame('Created', EventEnum::Created->getLabel());
        $this->assertSame('Updated', EventEnum::Updated->getLabel());
        $this->assertSame('Deleted', EventEnum::Deleted->getLabel());
        $this->assertSame('Restored', EventEnum::Restored->getLabel());
        $this->assertSame('Force Deleted', EventEnum::ForceDeleted->getLabel());
    }

    public function test_force_deleted_value_differs_from_eloquent_event(): void
    {
        $this->assertNotSame(
            EventEnum::ForceDeleted->value,
            EventEnum::ForceDeleted->eloquentEvent()
        );
        $this->assertSame('force_deleted', EventEnum::ForceDeleted->value);
        $this->assertSame('forceDeleted', EventEnum::ForceDeleted->eloquentEvent());
    }
}