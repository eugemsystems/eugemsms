<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Registry;

use Modules\Intelligence\Domain\DataObjects\HardwareScanRouteDefinition;

/**
 * Book J INT-04 §3 ⭐/BR-INT-04-007/008. Code owns the list — the same
 * register/get/all shape as `Modules\Comms\Domain\Registry\WidgetRegistry`
 * and this book set's other registries. Each entry's `resolver` calls
 * ITS OWNING module's own real, pre-existing Action — the one a manual
 * entry already calls (BR-INT-04-008) — never a second, lighter-touch
 * path built for hardware alone. `purpose` is also the source of every
 * hardware `api_clients` scoped ability this pass permits
 * (`{purpose}:write`, see `abilities()`), so a device can only ever be
 * scoped to a purpose this registry actually knows how to route.
 *
 * **Scope boundary.** This pass registers two real routes — `roll_call`
 * (`BRD-02`'s `MarkRollCallAction`) and `gate` (`BRD-02`'s own
 * `RecordCheckpointMovementAction`) — not every `device_source` column
 * the wider specification names. `attendance` (`ACA-04`) needs a
 * timetable-aware "which session is live right now" resolution this
 * pass does not build; registering it with a guessed resolver would be
 * worse than leaving it as a documented gap for a later pass.
 */
final class HardwareScanRouteRegistry
{
    /**
     * @var array<string, HardwareScanRouteDefinition>
     */
    private static array $routes = [];

    public static function register(HardwareScanRouteDefinition $route): void
    {
        self::$routes[$route->purpose] = $route;
    }

    public static function get(string $purpose): ?HardwareScanRouteDefinition
    {
        return self::$routes[$purpose] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function purposes(): array
    {
        return array_keys(self::$routes);
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return array_map(fn (string $purpose): string => "{$purpose}:write", self::purposes());
    }

    public static function clear(): void
    {
        self::$routes = [];
    }
}
