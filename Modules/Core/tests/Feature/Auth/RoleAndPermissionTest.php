<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\Actions\Auth\CloneRoleTemplateAction;
use Modules\Core\Domain\Actions\Auth\RevokeRoleAction;
use Modules\Core\Domain\Actions\Auth\UpdateRolePermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\CloneRoleData;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\DataObjects\Auth\RolePermissionData;
use Modules\Core\Domain\Exceptions\SystemRoleTemplateException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

it('assigns a role to a user in one school only (BR-CORE-05-012)', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $user = User::factory()->create();
    $role = Role::factory()->forSchool($schoolA->id)->create();

    app(AssignRoleAction::class)->execute(new RoleAssignmentData($user->id, $role->id, $schoolA->id));

    SchoolContext::set($schoolA);
    expect($user->hasRole($role->name))->toBeTrue();

    SchoolContext::set($schoolB);
    expect($user->fresh()->hasRole($role->name))->toBeFalse();
});

it('revokes a role from a user in a specific school', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $role = Role::factory()->forSchool($school->id)->create();

    app(AssignRoleAction::class)->execute(new RoleAssignmentData($user->id, $role->id, $school->id));
    app(RevokeRoleAction::class)->execute(new RoleAssignmentData($user->id, $role->id, $school->id));

    SchoolContext::set($school);
    expect($user->fresh()->hasRole($role->name))->toBeFalse();
});

it('clones a system role template into a school-owned, editable copy (BR-CORE-05-013)', function (): void {
    $school = School::factory()->create();
    $permission = Permission::factory()->create(['name' => 'core.user.view']);
    $template = Role::factory()->system()->create();
    $template->givePermissionTo($permission);

    $clone = app(CloneRoleTemplateAction::class)->execute(new CloneRoleData(
        sourceRoleId: $template->id,
        schoolId: $school->id,
        name: 'finance-director',
        displayName: 'Finance Director',
    ));

    expect($clone->school_id)->toBe($school->id)
        ->and($clone->is_system)->toBeFalse()
        ->and($clone->permissions->pluck('name')->all())->toBe(['core.user.view']);
});

it('refuses to modify a system template directly', function (): void {
    $permission = Permission::factory()->create();
    $template = Role::factory()->system()->create();

    app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData(
        $template->id,
        [new PermissionGrantData($permission->id, PermissionScope::School)],
    ));
})->throws(SystemRoleTemplateException::class);

it('updates a school role\'s permission grants with their scopes', function (): void {
    $school = School::factory()->create();
    $role = Role::factory()->forSchool($school->id)->create();
    $permission = Permission::factory()->create(['name' => 'academic.result.enter']);

    app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData(
        $role->id,
        [new PermissionGrantData($permission->id, PermissionScope::Assigned)],
    ));

    expect($role->refresh()->permissions->pluck('name')->all())->toBe(['academic.result.enter'])
        ->and($role->permissionScopes()->sole()->scope)->toBe(PermissionScope::Assigned);
});

it('resolves the widest scope when a user holds a permission via two roles (BR-CORE-05-015)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $permission = Permission::factory()->create(['name' => 'academic.result.enter']);

    $narrowRole = Role::factory()->forSchool($school->id)->create();
    $wideRole = Role::factory()->forSchool($school->id)->create();

    app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData($narrowRole->id, [new PermissionGrantData($permission->id, PermissionScope::Own)]));
    app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData($wideRole->id, [new PermissionGrantData($permission->id, PermissionScope::School)]));

    app(AssignRoleAction::class)->execute(new RoleAssignmentData($user->id, $narrowRole->id, $school->id));
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($user->id, $wideRole->id, $school->id));

    SchoolContext::set($school);

    $resolved = app(PermissionScopeResolver::class)->resolve($user->fresh(), 'academic.result.enter');

    expect($resolved)->toBe(PermissionScope::School);
});
