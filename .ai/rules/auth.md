---
paths:
  - Modules/Core/Domain/Support/Auth/SchoolTeamResolver.php
  - 'Modules/Core/Domain/Support/Auth/*.php'
---

# Auth

## spatie/laravel-permission team-id override must be cleared, not "restored to null"
PermissionRegistrar is a container singleton holding one long-lived team resolver instance. Any Action that temporarily calls `app(PermissionRegistrar::class)->setPermissionsTeamId($explicitSchoolId)` (e.g. AssignRoleAction, RevokeRoleAction assigning a role in a school other than the ambient one) must restore it afterward — but restoring via `setPermissionsTeamId($previousValue)` where $previousValue was captured as null (no ambient school set yet) permanently freezes the resolver on "no team" for the rest of the request/test, since it no longer falls back to reading SchoolContext. Fixed by making SchoolTeamResolver::setPermissionsTeamId(null) call an internal clear() (drop the override entirely) instead of storing null as a real override value. Any future custom PermissionsTeamResolver must do the same.

## permission_scope_resolver_school_param: PermissionScopeResolver needs an explicit schoolId on tenant-wide screens
`PermissionScopeResolver::resolve()`/`has()` (2026-09-12) take an optional trailing `?int $schoolId` — when omitted, they check spatie's AMBIENT team id (`PermissionRegistrar::getPermissionsTeamId()`, which tracks `SchoolContext`). That's correct for any screen using `InteractsWithSchool` (ambient IS the right school), but WRONG for a tenant-wide screen with no single active school (`Users\Index`/`Show`/`Form` — no `{school}` route param, a user can hold different grants in different schools). For those, pass the specific target school explicitly, e.g. `Users\Show::assignRole()` validates `assignRoleSchoolId` first, then calls `$this->authorizePermission('core.role.update', schoolId: (int) $this->assignRoleSchoolId)`.

Known gap (not yet built): there's no "does this user hold X in ANY of their schools" resolver method, needed before `Users\Index`/`Show`/`Form`'s own baseline `core.user.view`/`create`/`update` checks can be wired up (checking one arbitrary school would be wrong for a genuinely tenant-wide list) — see the docblocks on those three classes.

Direct (non-role) permission grants: `Modules\Core\Models\UserPermissionScope` (user_id, permission_id, school_id, scope) mirrors `RolePermissionScope` for grants made straight to a user via `Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction` / `Modules\Core\Livewire\Users\DirectPermissions`. `PermissionScopeResolver::resolve()` merges role-derived AND direct-grant scopes and returns the widest — a direct grant can widen a narrower role grant (or exist with no role at all), never narrow one.
