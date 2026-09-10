<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\Events\Auth\RoleAssigned;
use Modules\Core\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ACT-AssignRole (Book A CORE-05 §3/BR-CORE-05-012). Roles are assigned
 * per school — spatie's "team" scope is the school given on the DTO,
 * not whatever `SchoolContext` happens to be ambient, since a
 * school-admin screen may assign a role in a school other than the
 * caller's own active one (e.g. a group-level admin).
 */
final class AssignRoleAction extends Action
{
    public function execute(RoleAssignmentData $data): void
    {
        $user = User::findOrFail($data->userId);
        $role = Role::findOrFail($data->roleId);
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $this->transaction(function () use ($user, $role, $data, $registrar, $previousTeamId): void {
            $registrar->setPermissionsTeamId($data->schoolId);

            try {
                $user->assignRole($role);
            } finally {
                $registrar->setPermissionsTeamId($previousTeamId);
            }

            event(new RoleAssigned($data->userId, $data->roleId, $data->schoolId));
        });
    }
}
