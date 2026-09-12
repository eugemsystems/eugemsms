<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Core\Models\RolePermissionScope;
use Modules\Core\Models\UserPermissionScope;
use Spatie\Permission\PermissionRegistrar;

/**
 * Book A CORE-05 BR-CORE-05-014/015. A permission check resolves
 * (user, active_school, permission, scope) — scope narrows the record
 * set, it never widens it. When the user holds the permission at
 * multiple scopes — through different roles, or a direct grant on top
 * of a role (2026-09-12, user-requested: "i can also do direct
 * permissions to a certain user not on a role") — the widest wins.
 */
final class PermissionScopeResolver
{
    /**
     * Roles are already school-scoped by spatie's "teams" feature (the
     * active school comes from `SchoolTeamResolver`/`SchoolContext`), so
     * `$user->roles` here means "this user's roles in the active school".
     * Direct grants use the same ambient team id, read straight off
     * spatie's own registrar rather than `SchoolContext` directly, so
     * this stays correct under an explicit `setPermissionsTeamId()`
     * override too (e.g. mid-`UpdateUserPermissionsAction`).
     *
     * Pass `$schoolId` explicitly for a screen with no single active
     * school of its own (e.g. `Users\Show`, which manages a tenant-wide
     * user's roles across several schools at once) — this temporarily
     * overrides the team id for the query, then restores whatever was
     * ambient before, the same "explicit school, not ambient" pattern
     * `AssignRoleAction`/`UpdateUserPermissionsAction` already use.
     */
    public function resolve(User $user, string $permissionName, ?int $schoolId = null): ?PermissionScope
    {
        if ($schoolId === null) {
            return $this->resolveForActiveTeam($user, $permissionName);
        }

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($schoolId);

        try {
            return $this->resolveForActiveTeam($user, $permissionName);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }

    private function resolveForActiveTeam(User $user, string $permissionName): ?PermissionScope
    {
        $roleIds = $user->roles()->pluck('roles.id');

        $scopes = collect();

        if ($roleIds->isNotEmpty()) {
            $scopes = $scopes->merge(
                RolePermissionScope::query()
                    ->whereIn('role_id', $roleIds)
                    ->whereHas('permission', fn ($query) => $query->where('name', $permissionName))
                    ->pluck('scope'),
            );
        }

        $activeSchoolId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        if ($activeSchoolId !== null) {
            $scopes = $scopes->merge(
                UserPermissionScope::query()
                    ->where('user_id', $user->id)
                    ->where('school_id', $activeSchoolId)
                    ->whereHas('permission', fn ($query) => $query->where('name', $permissionName))
                    ->pluck('scope'),
            );
        }

        return $this->widestOf($scopes);
    }

    /**
     * @param  Collection<int, PermissionScope>  $scopes
     */
    private function widestOf(Collection $scopes): ?PermissionScope
    {
        if ($scopes->isEmpty()) {
            return null;
        }

        return $scopes->reduce(
            fn (?PermissionScope $widest, PermissionScope $scope): PermissionScope => $widest === null ? $scope : PermissionScope::widest($widest, $scope),
        );
    }

    public function has(User $user, string $permissionName, PermissionScope $atLeast, ?int $schoolId = null): bool
    {
        $scope = $this->resolve($user, $permissionName, $schoolId);

        return $scope !== null && $scope->isAtLeastAsWideAs($atLeast);
    }
}
