<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\Actions\Auth\UpdateRolePermissionsAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\DataObjects\Auth\RolePermissionData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Users\DirectPermissions;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;

function loadUsersPermissionsRouteForTest(): void
{
    if (! Route::has('users.permissions')) {
        require base_path('Modules/Core/routes/roles.php');
    }
}

/**
 * `core.role.update` is now enforced on this screen (2026-09-12) —
 * granted as a direct permission for the same reason RoleScreensTest's
 * identical helper does it that way.
 */
function assignedSchoolForDirectPermissions(User $user): School
{
    $school = School::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    $updatePermission = Permission::firstOrCreate(
        ['name' => 'core.role.update'],
        ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'role', 'action' => 'update'],
    );

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: [new PermissionGrantData($updatePermission->id, PermissionScope::School)],
    ));

    return $school;
}

beforeEach(function (): void {
    loadUsersPermissionsRouteForTest();
});

it('loads a user\'s existing direct permission grants and scopes', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $school = assignedSchoolForDirectPermissions($admin);
    $target = User::factory()->create(['tenant_id' => $tenant->id]);
    $permission = Permission::factory()->create(['name' => 'academic.result.enter', 'module_code' => 'ACA']);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $target->id,
        schoolId: $school->id,
        grants: [new PermissionGrantData($permission->id, PermissionScope::Section)],
    ));

    Livewire::actingAs($admin)
        ->test(DirectPermissions::class, ['school' => $school, 'user' => $target])
        ->set('activeModule', 'ACA')
        ->assertSet("granted.{$permission->id}", true)
        ->assertSet("scopes.{$permission->id}", 'section');
});

it('grants a direct permission to a user without touching any role', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $school = assignedSchoolForDirectPermissions($admin);
    $target = User::factory()->create(['tenant_id' => $tenant->id]);
    $permission = Permission::factory()->create(['module_code' => 'CORE', 'name' => 'core.user.view']);

    Livewire::actingAs($admin)
        ->test(DirectPermissions::class, ['school' => $school, 'user' => $target])
        ->set('activeModule', 'CORE')
        ->call('toggleGrant', $permission->id)
        ->set("scopes.{$permission->id}", PermissionScope::School->value)
        ->call('save')
        ->assertHasNoErrors();

    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
    expect($target->fresh()->hasPermissionTo($permission))->toBeTrue()
        ->and($target->fresh()->roles)->toHaveCount(0);
});

it('revokes a direct permission on save when its grant is toggled back off', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $school = assignedSchoolForDirectPermissions($admin);
    $target = User::factory()->create(['tenant_id' => $tenant->id]);
    $permission = Permission::factory()->create(['module_code' => 'CORE']);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $target->id,
        schoolId: $school->id,
        grants: [new PermissionGrantData($permission->id, PermissionScope::Own)],
    ));

    Livewire::actingAs($admin)
        ->test(DirectPermissions::class, ['school' => $school, 'user' => $target])
        ->set('activeModule', 'CORE')
        ->call('toggleGrant', $permission->id)
        ->call('save')
        ->assertHasNoErrors();

    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
    expect($target->fresh()->hasPermissionTo($permission))->toBeFalse();
});

it('a user\'s effective permission scope is the widest of their role grant and any direct grant', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $school = assignedSchoolForDirectPermissions($admin);
    $target = User::factory()->create(['tenant_id' => $tenant->id]);
    $permission = Permission::factory()->create(['name' => 'academic.result.enter', 'module_code' => 'ACA']);

    $role = Role::factory()->forSchool($school->id)->create();
    app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData(
        $role->id,
        [new PermissionGrantData($permission->id, PermissionScope::Own)],
    ));
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($target->id, $role->id, $school->id));

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $target->id,
        schoolId: $school->id,
        grants: [new PermissionGrantData($permission->id, PermissionScope::School)],
    ));

    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
    $resolved = app(PermissionScopeResolver::class)->resolve($target->fresh(), 'academic.result.enter');

    expect($resolved)->toBe(PermissionScope::School);
});
