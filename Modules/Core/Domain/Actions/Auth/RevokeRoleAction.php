<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RevokeRoleAction extends Action
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
                $user->removeRole($role);
            } finally {
                $registrar->setPermissionsTeamId($previousTeamId);
            }
        });
    }
}
