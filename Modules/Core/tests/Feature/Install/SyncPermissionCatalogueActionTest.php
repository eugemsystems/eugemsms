<?php

use Modules\Core\Domain\Actions\Install\SyncPermissionCatalogueAction;
use Modules\Core\Domain\DataObjects\Install\SyncPermissionCatalogueData;
use Modules\Core\Domain\Registry\PermissionRegistry;
use Modules\Core\Models\Permission;

/**
 * Same "swap the registry wholesale, restore in `finally`" pattern as
 * `RolloverEngineTest`'s `withRolloverHandlers()` — CORE-05's own
 * registered catalogue stays registered outside the callback, only the
 * test body sees the replacement.
 */
function withPermissionCatalogue(array $modules, Closure $callback): mixed
{
    $original = PermissionRegistry::all();
    PermissionRegistry::clear();

    foreach ($modules as $moduleCode => $permissions) {
        PermissionRegistry::register($moduleCode, $permissions);
    }

    try {
        return $callback();
    } finally {
        PermissionRegistry::clear();

        foreach ($original as $moduleCode => $permissions) {
            PermissionRegistry::register($moduleCode, $permissions);
        }
    }
}

it('creates a permission row for everything registered, deriving module/resource/action from the path', function (): void {
    withPermissionCatalogue(['TEST' => [
        'widget.view' => ['description' => 'View widgets.'],
        'widget.delete' => ['description' => 'Delete widgets.', 'dangerous' => true],
    ]], function (): void {
        $result = app(SyncPermissionCatalogueAction::class)->execute(new SyncPermissionCatalogueData);

        expect($result->created)->toBe(2)
            ->and($result->updated)->toBe(0);

        $view = Permission::where('name', 'test.widget.view')->sole();
        expect($view->module_code)->toBe('TEST')
            ->and($view->resource)->toBe('widget')
            ->and($view->action)->toBe('view')
            ->and($view->description)->toBe('View widgets.')
            ->and($view->is_dangerous)->toBeFalse();

        $delete = Permission::where('name', 'test.widget.delete')->sole();
        expect($delete->is_dangerous)->toBeTrue();
    });
});

it('is idempotent: re-running updates in place instead of creating duplicates', function (): void {
    withPermissionCatalogue(['TEST' => ['widget.view' => ['description' => 'View widgets.']]], function (): void {
        app(SyncPermissionCatalogueAction::class)->execute(new SyncPermissionCatalogueData);
    });

    $result = withPermissionCatalogue(['TEST' => ['widget.view' => ['description' => 'View all widgets.']]], function () {
        return app(SyncPermissionCatalogueAction::class)->execute(new SyncPermissionCatalogueData);
    });

    expect($result->created)->toBe(0)
        ->and($result->updated)->toBe(1)
        ->and(Permission::where('name', 'test.widget.view')->count())->toBe(1)
        ->and(Permission::where('name', 'test.widget.view')->sole()->description)->toBe('View all widgets.');
});

it('registers the real CORE-05 catalogue, covering every permission the admin screens already reference', function (): void {
    app(SyncPermissionCatalogueAction::class)->execute(new SyncPermissionCatalogueData);

    $names = Permission::where('module_code', 'CORE')->pluck('name');

    expect($names)->toContain('core.user.view', 'core.user.impersonate', 'core.role.view', 'core.role.update', 'core.audit.view', 'core.session.manage');
});
