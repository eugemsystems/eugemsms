<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Registry;

use Modules\Comms\Domain\DataObjects\AutomationEntityDefinition;

/**
 * Book I COM-02 §3 ⭐/BR-COM-02-003 (AC-COM-02-003). Mirrors
 * `Modules\Compliance\Domain\Registry\PersonalDataTableRegistry`'s own
 * shape. **Scope boundary**: this pass registers one real, fully
 * working entity (`invoice`, see `CommsServiceProvider::registerAutomationEntities()`)
 * rather than every scannable entity across this codebase's other
 * modules — the registry architecture is complete and extensible;
 * registering every module's own entities is real follow-up work, the
 * same documented boundary `PersonalDataTableRegistry` already drew.
 */
final class AutomationEntityRegistry
{
    /**
     * @var array<string, AutomationEntityDefinition>
     */
    private static array $entities = [];

    public static function register(AutomationEntityDefinition $definition): void
    {
        self::$entities[$definition->entityKey] = $definition;
    }

    public static function get(string $entityKey): ?AutomationEntityDefinition
    {
        return self::$entities[$entityKey] ?? null;
    }

    public static function isFieldAllowed(string $entityKey, string $field): bool
    {
        $definition = self::get($entityKey);

        return $definition !== null && in_array($field, $definition->allowedFields, true);
    }

    /**
     * @return array<string, AutomationEntityDefinition>
     */
    public static function all(): array
    {
        return self::$entities;
    }

    public static function clear(): void
    {
        self::$entities = [];
    }
}
