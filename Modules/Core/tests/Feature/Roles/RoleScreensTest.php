<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateRolePermissionsAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\RolePermissionData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Roles\Editor as RolesEditor;
use Modules\Core\Livewire\Roles\Index as RolesIndex;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

/**
 * CoreServiceProvider does not yet load routes/roles.php (a parallel
 * task registers it separately, per the instructions this screen was
 * built under) — the views under test call route('roles.index', ...)
 * etc., so the route file is required directly here rather than relying
 * on the provider.
 */
function loadRoleRoutesForTest(): void
{
    if (! Route::has('roles.index')) {
        require base_path('Modules/Core/routes/roles.php');
    }
}

/**
 * `core.role.view`/`core.role.update` are now enforced on every screen
 * in this file (2026-09-12) — grant both as direct permissions (rather
 * than via a role, to keep this helper independent of role-assignment
 * machinery) so every test's acting user can actually reach the screen
 * under test.
 */
function assignedSchoolForRoleScreens(User $user): School
{
    $school = School::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    $viewPermission = Permission::firstOrCreate(
        ['name' => 'core.role.view'],
        ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'role', 'action' => 'view'],
    );
    $updatePermission = Permission::firstOrCreate(
        ['name' => 'core.role.update'],
        ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'role', 'action' => 'update'],
    );

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: [
            new PermissionGrantData($viewPermission->id, PermissionScope::School),
            new PermissionGrantData($updatePermission->id, PermissionScope::School),
        ],
    ));

    return $school;
}

beforeEach(function (): void {
    loadRoleRoutesForTest();
});

it('lists school-owned roles and system templates, distinguishing the two', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForRoleScreens($user);
    $schoolRole = Role::factory()->forSchool($school->id)->create(['display_name' => 'Custom Bursar']);
    $template = Role::factory()->system()->create(['display_name' => 'Head of Department']);

    Livewire::actingAs($user)
        ->test(RolesIndex::class, ['school' => $school])
        ->assertSee('Custom Bursar')
        ->assertSee('Head of Department');
});

it('does not show a role owned by a different school', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForRoleScreens($user);
    $otherSchool = School::factory()->create();
    Role::factory()->forSchool($otherSchool->id)->create(['display_name' => 'Other School Role']);

    Livewire::actingAs($user)
        ->test(RolesIndex::class, ['school' => $school])
        ->assertDontSee('Other School Role');
});

it('clones a system template into a school-owned role and redirects to its editor (BR-CORE-05-013)', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForRoleScreens($user);
    $template = Role::factory()->system()->create(['display_name' => 'Class Teacher']);

    $component = Livewire::actingAs($user)
        ->test(RolesIndex::class, ['school' => $school])
        ->call('openCloneModal', $template->id)
        ->set('newName', 'class-teacher-custom')
        ->set('newDisplayName', 'Class Teacher (Custom)')
        ->call('cloneRole')
        ->assertHasNoErrors();

    $clone = Role::where('school_id', $school->id)->where('name', 'class-teacher-custom')->sole();

    expect($clone->is_system)->toBeFalse();

    $component->assertRedirect(route('roles.edit', [$school, $clone]));
});

it('refuses to open the editor for a system role template and sends the admin back to the index', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForRoleScreens($user);
    $template = Role::factory()->system()->create();

    Livewire::actingAs($user)
        ->test(RolesEditor::class, ['school' => $school, 'role' => $template])
        ->assertRedirect(route('roles.index', $school));
});

it('loads a school role\'s existing permission grants and scopes into the editor', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForRoleScreens($user);
    $role = Role::factory()->forSchool($school->id)->create();
    $permission = Permission::factory()->create(['name' => 'academic.result.enter', 'module_code' => 'ACA']);

    app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData(
        $role->id,
        [new PermissionGrantData($permission->id, PermissionScope::Assigned)],
    ));

    Livewire::actingAs($user)
        ->test(RolesEditor::class, ['school' => $school, 'role' => $role])
        ->set('activeModule', 'ACA')
        ->assertSet("granted.{$permission->id}", true)
        ->assertSet("scopes.{$permission->id}", 'assigned');
});

it('toggles a permission grant locally, defaulting a freshly granted permission to own scope', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForRoleScreens($user);
    $role = Role::factory()->forSchool($school->id)->create();
    $permission = Permission::factory()->create(['module_code' => 'CORE']);

    Livewire::actingAs($user)
        ->test(RolesEditor::class, ['school' => $school, 'role' => $role])
        ->set('activeModule', 'CORE')
        ->call('toggleGrant', $permission->id)
        ->assertSet("granted.{$permission->id}", true)
        ->assertSet("scopes.{$permission->id}", 'own')
        ->call('toggleGrant', $permission->id)
        ->assertSet("granted.{$permission->id}", false);
});

it('saves the permission matrix via UpdateRolePermissionsAction', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForRoleScreens($user);
    $role = Role::factory()->forSchool($school->id)->create();
    $permission = Permission::factory()->create(['module_code' => 'FIN', 'name' => 'finance.receipt.create']);

    Livewire::actingAs($user)
        ->test(RolesEditor::class, ['school' => $school, 'role' => $role])
        ->set('activeModule', 'FIN')
        ->call('toggleGrant', $permission->id)
        ->set("scopes.{$permission->id}", PermissionScope::School->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['finance.receipt.create'])
        ->and($role->permissionScopes()->sole()->scope)->toBe(PermissionScope::School);
});

it('revokes a permission on save when its grant is toggled back off', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForRoleScreens($user);
    $role = Role::factory()->forSchool($school->id)->create();
    $permission = Permission::factory()->create(['module_code' => 'FIN']);

    app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData(
        $role->id,
        [new PermissionGrantData($permission->id, PermissionScope::Own)],
    ));

    Livewire::actingAs($user)
        ->test(RolesEditor::class, ['school' => $school, 'role' => $role])
        ->set('activeModule', 'FIN')
        ->call('toggleGrant', $permission->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe([]);
});
