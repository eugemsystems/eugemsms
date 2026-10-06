<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;

/**
 * Answers "may this user do X?" for screens that mix several permissions.
 * It never replaces `authorizePermission()` on the action itself.
 */
trait ChecksPermissions
{
    protected function holds(string $permission, PermissionScope $atLeast = PermissionScope::Own): bool
    {
        $user = Auth::user();

        return $user !== null && app(PermissionScopeResolver::class)->has($user, $permission, $atLeast);
    }
}
