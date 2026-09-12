<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

/**
 * Book A Part 1.8 — "Permissions are registered in code by each module's
 * service provider and synced to the database on deploy. A permission
 * that is not registered cannot be assigned." Each module's
 * `ServiceProvider::boot()` calls `register()` with its own catalogue;
 * `SyncPermissionCatalogueAction` (Book A CORE-01 §6, run via
 * `serp:sync-permissions` and as part of `RunUpgradeAction`) is what
 * actually writes `Permission` rows from whatever is registered here —
 * this class itself never touches the database, same division of
 * responsibility as `SeedPackRegistry`/`ScheduledTaskRegistry`.
 *
 * A permission path is `resource.action` (no module prefix — the module
 * code is the array key here and becomes the dotted name's first segment
 * when synced, e.g. module `CORE` + path `user.impersonate` becomes the
 * permission name `core.user.impersonate`).
 */
final class PermissionRegistry
{
    /**
     * @var array<string, array<string, array{description: ?string, dangerous: bool}>>
     */
    private static array $modules = [];

    /**
     * @param  array<int|string, array{description?: string, dangerous?: bool}|string>  $permissions
     *                                                                                                Either `'resource.action' => ['description' => ..., 'dangerous' => true]`
     *                                                                                                or a bare list of `'resource.action'` strings with no metadata.
     */
    public static function register(string $moduleCode, array $permissions): void
    {
        $moduleCode = strtoupper($moduleCode);
        $normalised = [];

        foreach ($permissions as $key => $meta) {
            if (is_int($key)) {
                if (! is_string($meta)) {
                    continue;
                }

                $path = $meta;
                $meta = [];
            } else {
                $path = $key;
            }

            $normalised[$path] = [
                'description' => $meta['description'] ?? null,
                'dangerous' => $meta['dangerous'] ?? false,
            ];
        }

        self::$modules[$moduleCode] = array_merge(self::$modules[$moduleCode] ?? [], $normalised);
    }

    /**
     * @return array<string, array<string, array{description: ?string, dangerous: bool}>>
     */
    public static function all(): array
    {
        return self::$modules;
    }

    public static function clear(): void
    {
        self::$modules = [];
    }
}
