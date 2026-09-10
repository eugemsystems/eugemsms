---
paths:
  - Modules/Core/Domain/Support/Auth/SchoolTeamResolver.php
---

# Auth

## spatie/laravel-permission team-id override must be cleared, not "restored to null"
PermissionRegistrar is a container singleton holding one long-lived team resolver instance. Any Action that temporarily calls `app(PermissionRegistrar::class)->setPermissionsTeamId($explicitSchoolId)` (e.g. AssignRoleAction, RevokeRoleAction assigning a role in a school other than the ambient one) must restore it afterward — but restoring via `setPermissionsTeamId($previousValue)` where $previousValue was captured as null (no ambient school set yet) permanently freezes the resolver on "no team" for the rest of the request/test, since it no longer falls back to reading SchoolContext. Fixed by making SchoolTeamResolver::setPermissionsTeamId(null) call an internal clear() (drop the override entirely) instead of storing null as a real override value. Any future custom PermissionsTeamResolver must do the same.
