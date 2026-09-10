<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\CloneRoleData;
use Modules\Core\Models\Role;
use Modules\Core\Models\RolePermissionScope;

/**
 * ACT-CloneRoleTemplate (Book A CORE-05 §3/BR-CORE-05-013). A system
 * template (`is_system = 1`) can never be deleted or renamed, but any
 * school may clone it into its own editable copy — this Action is that
 * clone, carrying the source's permission-scope grants across.
 * `role_has_permissions` isn't team-scoped by spatie, so this needs no
 * team-id juggling — `school_id` is set explicitly on the new role.
 */
final class CloneRoleTemplateAction extends Action
{
    public function execute(CloneRoleData $data): Role
    {
        $source = Role::findOrFail($data->sourceRoleId);

        return $this->transaction(function () use ($source, $data): Role {
            // `Role::query()->create()` rather than spatie's static
            // `Role::create()` override: that override returns
            // `RoleContract|Role` per its own PHPDoc, which loses the
            // concrete return type here. Every side effect that
            // override adds beyond a plain create() — defaulting
            // `guard_name`, defaulting `school_id` from the ambient
            // team id — is moot since both are given explicitly below.
            $clone = Role::query()->create([
                'school_id' => $data->schoolId,
                'name' => $data->name,
                'display_name' => $data->displayName,
                'description' => $source->description,
                'guard_name' => $source->guard_name,
                'is_system' => false,
                'is_vendor_only' => false,
                'category' => $source->category,
            ]);

            $clone->syncPermissions($source->permissions);

            foreach ($source->permissionScopes as $sourceScope) {
                RolePermissionScope::create([
                    'role_id' => $clone->id,
                    'permission_id' => $sourceScope->permission_id,
                    'scope' => $sourceScope->scope,
                ]);
            }

            return $clone;
        });
    }
}
