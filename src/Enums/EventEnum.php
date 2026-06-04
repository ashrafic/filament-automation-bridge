<?php

namespace Ashrafic\FilamentWebhookBridge\Enums;

enum EventEnum: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case ForceDeleted = 'force_deleted';

    public function eloquentEvent(): string
    {
        return match ($this) {
            self::Created => 'created',
            self::Updated => 'updated',
            self::Deleted => 'deleted',
            self::Restored => 'restored',
            self::ForceDeleted => 'forceDeleted',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
            self::Restored => 'Restored',
            self::ForceDeleted => 'Force Deleted',
        };
    }
}
