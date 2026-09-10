<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Registry;

use Modules\Comms\Domain\DataObjects\AutomationEventDefinition;

/**
 * Book I COM-02 §4/BR-COM-02-001 (AC-COM-02-003). **Scope boundary**:
 * this pass registers a small, real starting set of already-fired
 * domain events (see `CommsServiceProvider::registerAutomationEvents()`)
 * rather than every event across this codebase's other ~18 modules —
 * the same documented boundary `AutomationEntityRegistry` and
 * `Modules\Compliance\Domain\Registry\PersonalDataTableRegistry` both
 * already draw.
 */
final class AutomationEventRegistry
{
    /**
     * @var array<string, AutomationEventDefinition>
     */
    private static array $events = [];

    public static function register(AutomationEventDefinition $definition): void
    {
        self::$events[$definition->eventName] = $definition;
    }

    public static function get(string $eventName): ?AutomationEventDefinition
    {
        return self::$events[$eventName] ?? null;
    }

    public static function isRegistered(string $eventName): bool
    {
        return self::get($eventName) !== null;
    }

    /**
     * @return array<string, AutomationEventDefinition>
     */
    public static function all(): array
    {
        return self::$events;
    }

    public static function clear(): void
    {
        self::$events = [];
    }
}
