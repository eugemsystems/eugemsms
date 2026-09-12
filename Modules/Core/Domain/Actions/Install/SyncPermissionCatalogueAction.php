<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Install\PermissionSyncResult;
use Modules\Core\Domain\DataObjects\Install\SyncPermissionCatalogueData;
use Modules\Core\Domain\Registry\PermissionRegistry;
use Modules\Core\Models\Permission;

/**
 * ACT-SyncPermissionCatalogue (Book A Part 1.8): writes every permission
 * every module has registered via `PermissionRegistry::register()` into
 * the `permissions` table, upserting by `name` so re-running is always
 * safe — a module changing a permission's description/dangerous flag
 * just updates the existing row, it never creates a duplicate. Nothing
 * is ever deleted here: a permission a module stops registering (e.g. a
 * renamed action) is left in place rather than silently dropped out from
 * under any role that already holds it — removing a stale permission is
 * a deliberate, separate operation, not a side effect of a routine sync.
 *
 * Run via `php artisan serp:sync-permissions` and automatically as part
 * of `RunUpgradeAction`, matching the spec's "synced to the database on
 * deploy."
 */
final class SyncPermissionCatalogueAction extends Action
{
    public function execute(SyncPermissionCatalogueData $data): PermissionSyncResult
    {
        return $this->transaction(function (): PermissionSyncResult {
            $created = 0;
            $updated = 0;
            $total = 0;

            foreach (PermissionRegistry::all() as $moduleCode => $permissions) {
                foreach ($permissions as $path => $meta) {
                    $total++;

                    [$resource, $action] = $this->splitPath($path);
                    $name = strtolower($moduleCode).'.'.$path;

                    $attributes = [
                        'module_code' => $moduleCode,
                        'resource' => $resource,
                        'action' => $action,
                        'description' => $meta['description'],
                        'is_dangerous' => $meta['dangerous'],
                    ];

                    $permission = Permission::query()
                        ->where('name', $name)
                        ->where('guard_name', 'web')
                        ->first();

                    if ($permission === null) {
                        Permission::create([
                            'name' => $name,
                            'guard_name' => 'web',
                            ...$attributes,
                        ]);

                        $created++;

                        continue;
                    }

                    $permission->fill($attributes);

                    if ($permission->isDirty()) {
                        $permission->save();
                        $updated++;
                    }
                }
            }

            return new PermissionSyncResult(created: $created, updated: $updated, total: $total);
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPath(string $path): array
    {
        $parts = explode('.', $path, 2);

        return [$parts[0], $parts[1] ?? $parts[0]];
    }
}
