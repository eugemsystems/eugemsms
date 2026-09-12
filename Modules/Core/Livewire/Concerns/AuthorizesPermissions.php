<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;

/**
 * Book A CORE-05 BR-CORE-05-014/015, standardised (2026-09-12,
 * user-requested: "permissions must be added and checked when coding").
 * Every Livewire screen a permission has been registered for should call
 * `$this->authorizePermission('module.resource.action')` from `mount()`
 * (and from any individual mutating method whose action is more
 * sensitive than the screen's own baseline view permission) rather than
 * leaving it as a "not yet enforced" docblock note — that note was only
 * ever true because no permission catalogue existed yet to check
 * against; now that `PermissionRegistry`/`SyncPermissionCatalogueAction`
 * are real, a new screen with no enforcement call is a bug, not a
 * deferred TODO.
 *
 * This is deliberately the ONLY thing this trait does — it aborts 403,
 * it does not also load `SchoolContext` or anything else `InteractsWithSchool`
 * already covers. Use both traits together on any screen that needs them.
 */
trait AuthorizesPermissions
{
    /**
     * `$schoolId` is only needed on a screen with no single active school
     * of its own (e.g. `Users\Show`, which manages roles across several
     * schools per request) — pass the specific school the action being
     * authorised targets. A screen using `InteractsWithSchool` doesn't
     * need it: `PermissionScopeResolver` already reads the ambient team
     * id `loadSchool()` sets.
     */
    protected function authorizePermission(string $permissionName, PermissionScope $atLeast = PermissionScope::Own, ?int $schoolId = null): void
    {
        $user = Auth::user();

        abort_unless(
            $user !== null && app(PermissionScopeResolver::class)->has($user, $permissionName, $atLeast, $schoolId),
            403,
        );
    }
}
