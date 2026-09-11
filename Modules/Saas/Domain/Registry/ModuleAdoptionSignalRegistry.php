<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Registry;

use Modules\Saas\Domain\DataObjects\ModuleAdoptionSignalDefinition;

/**
 * Book J SAA-03 §3 ⭐/BR-SAA-03-006 ⭐. Code owns the list — mirrors
 * `Modules\Intelligence\Domain\Registry\RiskIndicatorRegistry`'s own
 * register/get/all shape. **Scope boundary**: this pass registers a
 * small, real starting set of signals (see
 * `SaasServiceProvider::registerModuleAdoptionSignals()`) mirroring
 * the specification's own worked example (`FIN-01`'s `journal_posted`,
 * `BRD-06`'s `sick_bay_admission_recorded`) — not one signal per
 * module in the catalogue, the same documented boundary every other
 * registry in this book set draws.
 */
final class ModuleAdoptionSignalRegistry
{
    /**
     * @var array<string, ModuleAdoptionSignalDefinition>
     */
    private static array $signals = [];

    public static function register(ModuleAdoptionSignalDefinition $signal): void
    {
        self::$signals[$signal->moduleCode] = $signal;
    }

    public static function get(string $moduleCode): ?ModuleAdoptionSignalDefinition
    {
        return self::$signals[$moduleCode] ?? null;
    }

    /**
     * @return array<int, ModuleAdoptionSignalDefinition>
     */
    public static function all(): array
    {
        return array_values(self::$signals);
    }

    public static function clear(): void
    {
        self::$signals = [];
    }
}
