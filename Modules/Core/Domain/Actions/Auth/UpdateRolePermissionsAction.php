<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\RolePermissionData;
use Modules\Core\Domain\Events\Auth\PermissionsChanged;
use Modules\Core\Domain\Exceptions\SystemRoleTemplateException;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\RolePermissionScope;

/**
 * ACT-UpdateRolePermissions (Book A CORE-05 §3/BR-CORE-05-013). A
 * system template's permission set is fixed — only a clone can be
 * edited — so this refuses outright on `is_system`, rather than
 * silently no-op'ing.
 */
final class UpdateRolePermissionsAction extends Action
{
    public function execute(RolePermissionData $data): Role
    {
        $role = Role::findOrFail($data->roleId);

        if ($role->is_system) {
            throw new SystemRoleTemplateException('System role templates cannot be modified directly. Clone it first.');
        }

        return $this->transaction(function () use ($role, $data): Role {
            $permissionIds = array_map(fn ($grant) => $grant->permissionId, $data->grants);
            $permissions = Permission::findMany($permissionIds);

            $role->syncPermissions($permissions);

            RolePermissionScope::where('role_id', $role->id)->delete();

            foreach ($data->grants as $grant) {
                RolePermissionScope::create([
                    'role_id' => $role->id,
                    'permission_id' => $grant->permissionId,
                    'scope' => $grant->scope,
                ]);
            }

            event(new PermissionsChanged($role->id));

            return $role->refresh();
        });
    }
}
