<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

use App\Models\User;
use Modules\Core\Models\RolePermissionScope;

/**
 * Book A CORE-05 BR-CORE-05-014/015. A permission check resolves
 * (user, active_school, permission, scope) — scope narrows the record
 * set, it never widens it. When the user holds the permission at
 * multiple scopes through different roles, the widest wins.
 */
final class PermissionScopeResolver
{
    /**
     * Roles are already school-scoped by spatie's "teams" feature (the
     * active school comes from `SchoolTeamResolver`/`SchoolContext`), so
     * `$user->roles` here means "this user's roles in the active school".
     */
    public function resolve(User $user, string $permissionName): ?PermissionScope
    {
        $roleIds = $user->roles()->pluck('roles.id');

        if ($roleIds->isEmpty()) {
            return null;
        }

        $scopes = RolePermissionScope::query()
            ->whereIn('role_id', $roleIds)
            ->whereHas('permission', fn ($query) => $query->where('name', $permissionName))
            ->pluck('scope');

        if ($scopes->isEmpty()) {
            return null;
        }

        return $scopes->reduce(
            fn (?PermissionScope $widest, PermissionScope $scope): PermissionScope => $widest === null ? $scope : PermissionScope::widest($widest, $scope),
        );
    }

    public function has(User $user, string $permissionName, PermissionScope $atLeast): bool
    {
        $scope = $this->resolve($user, $permissionName);

        return $scope !== null && $scope->isAtLeastAsWideAs($atLeast);
    }
}
