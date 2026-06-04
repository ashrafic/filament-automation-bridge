<?php

namespace Ashrafic\FilamentWebhookBridge\Triggers;

use Ashrafic\FilamentWebhookBridge\Contracts\TriggerContract;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use RuntimeException;

class TriggerManager
{
    /** @var array<string, TriggerContract> */
    protected array $triggers = [];

    /** @var array<int, \Closure> cleanup callables for active trigger subscriptions */
    protected array $cleanupCallbacks = [];

    /** @var array<string, int[]> Mapping of event class => [trigger IDs] */
    protected static array $subscribedEvents = [];

    public function register(string|TriggerContract $trigger): void
    {
        if (is_string($trigger)) {
            $trigger = app($trigger);
        }
        $this->triggers[$trigger::type()] = $trigger;
    }

    public function get(string $type): TriggerContract
    {
        if (! isset($this->triggers[$type])) {
            throw new RuntimeException("Unknown trigger type: {$type}");
        }

        return $this->triggers[$type];
    }

    /** @return array<string, TriggerContract> */
    public function all(): array
    {
        return $this->triggers;
    }

    /** @return array<string, string> [type => name] for select dropdowns */
    public function options(): array
    {
        $options = [];
        foreach ($this->triggers as $type => $trigger) {
            $options[$type] = $trigger::name();
        }

        return $options;
    }

    /** Subscribe a trigger for listening */
    public function subscribe(WebhookTrigger $trigger): void
    {
        $cleanup = $this->get($trigger->trigger_type)->subscribe($trigger);
        if ($cleanup) {
            $this->cleanupCallbacks[$trigger->id] = $cleanup;
        }
    }

    /** Unsubscribe a trigger */
    public function unsubscribe(WebhookTrigger $trigger): void
    {
        $this->get($trigger->trigger_type)->unsubscribe($trigger);
        if (isset($this->cleanupCallbacks[$trigger->id])) {
            ($this->cleanupCallbacks[$trigger->id])();
            unset($this->cleanupCallbacks[$trigger->id]);
        }
    }

    public static function addSubscribedEvent(string $eventClass, int $triggerId): void
    {
        if (! isset(static::$subscribedEvents[$eventClass])) {
            static::$subscribedEvents[$eventClass] = [];
        }

        if (! in_array($triggerId, static::$subscribedEvents[$eventClass], true)) {
            static::$subscribedEvents[$eventClass][] = $triggerId;
        }
    }

    public static function removeSubscribedEvent(string $eventClass, int $triggerId): void
    {
        if (! isset(static::$subscribedEvents[$eventClass])) {
            return;
        }

        static::$subscribedEvents[$eventClass] = array_values(
            array_filter(static::$subscribedEvents[$eventClass], fn ($id) => $id !== $triggerId),
        );

        if (empty(static::$subscribedEvents[$eventClass])) {
            unset(static::$subscribedEvents[$eventClass]);
        }
    }

    public static function getTriggerIdsForEvent(string $eventClass): array
    {
        return static::$subscribedEvents[$eventClass] ?? [];
    }

    public static function hasEventSubscriptions(): bool
    {
        return ! empty(static::$subscribedEvents);
    }
}
