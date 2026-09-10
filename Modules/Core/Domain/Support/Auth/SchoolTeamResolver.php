<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Support\SchoolContext;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Book A CORE-05 §2. spatie/laravel-permission's "team" is this app's
 * school. Roles/permissions resolve against `SchoolContext` by default;
 * an explicit `setPermissionsTeamId()` call (used when granting a role
 * in a school other than the caller's active one) overrides it for the
 * remainder of the request.
 */
final class SchoolTeamResolver implements PermissionsTeamResolver
{
    private int|string|null $override = null;

    private bool $hasOverride = false;

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->hasOverride ? $this->override : SchoolContext::currentId();
    }

    /**
     * A `null` id clears the override and falls back to tracking
     * `SchoolContext` again, rather than freezing on "no team forever"
     * — callers that temporarily override the team id (e.g.
     * `AssignRoleAction` granting a role in a school other than the
     * caller's active one) restore state by passing back whatever the
     * ambient team id was *before* their override, which is `null` when
     * no school was active yet. Without this, that restore would freeze
     * every later permission check in this request at "no team", since
     * spatie's own `PermissionRegistrar` never calls `clear()` for us.
     */
    public function setPermissionsTeamId(Model|int|string|null $id): void
    {
        if ($id === null) {
            $this->clear();

            return;
        }

        $this->override = $id instanceof Model ? $id->getKey() : $id;
        $this->hasOverride = true;
    }

    public function clear(): void
    {
        $this->override = null;
        $this->hasOverride = false;
    }
}
