<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Events\Auth\UserPermissionsChanged;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\UserPermissionScope;
use Spatie\Permission\PermissionRegistrar;

/**
 * ACT-UpdateUserPermissions (Book A CORE-05 §2 extension, 2026-09-12,
 * user-requested): direct permission grants to one specific user in one
 * specific school, independent of any role they hold there — replaces
 * the user's whole direct-grant set for that school in one call, same
 * "no partial save" shape as `UpdateRolePermissionsAction`.
 *
 * The school is the one given on the DTO, not whatever `SchoolContext`
 * happens to be ambient (same reasoning as `AssignRoleAction`) — spatie's
 * "team" scope is explicitly overridden for the duration of the write via
 * `PermissionRegistrar`, then restored, so this Action is correct
 * regardless of which school the calling screen currently has active.
 */
final class UpdateUserPermissionsAction extends Action
{
    public function execute(UserPermissionData $data): User
    {
        $user = User::findOrFail($data->userId);
        $school = School::findOrFail($data->schoolId);
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        return $this->transaction(function () use ($user, $school, $data, $registrar, $previousTeamId): User {
            $registrar->setPermissionsTeamId($school->id);

            try {
                $permissionIds = array_map(fn ($grant) => $grant->permissionId, $data->grants);
                $permissions = Permission::findMany($permissionIds);

                $user->syncPermissions($permissions);

                UserPermissionScope::where('user_id', $user->id)
                    ->where('school_id', $school->id)
                    ->delete();

                foreach ($data->grants as $grant) {
                    UserPermissionScope::create([
                        'user_id' => $user->id,
                        'school_id' => $school->id,
                        'permission_id' => $grant->permissionId,
                        'scope' => $grant->scope,
                    ]);
                }
            } finally {
                $registrar->setPermissionsTeamId($previousTeamId);
            }

            event(new UserPermissionsChanged($user->id, $school->id));

            return $user->refresh();
        });
    }
}
