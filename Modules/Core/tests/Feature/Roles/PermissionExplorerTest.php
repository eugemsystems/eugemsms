<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\Actions\Auth\UpdateRolePermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\DataObjects\Auth\RolePermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Livewire\Permissions\Explorer as PermissionsExplorer;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

/**
 * See RoleScreensTest.php's identically-named helper for why this is
 * required directly rather than relying on CoreServiceProvider — a
 * parallel task registers routes/roles.php there separately. Pest test
 * files don't share plain functions when run individually (see
 * .ai/rules/tests.md), so this is duplicated rather than imported.
 */
function loadRoleRoutesForExplorerTest(): void
{
    if (! Route::has('roles.index')) {
        require base_path('Modules/Core/routes/roles.php');
    }
}

function assignedSchoolForPermissionExplorer(User $user): School
{
    $school = School::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    return $school;
}

beforeEach(function (): void {
    loadRoleRoutesForExplorerTest();
});

it('shows every role in the school granting a chosen permission, with its scope and holder count', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForPermissionExplorer($user);
    $permission = Permission::factory()->create(['name' => 'finance.receipt.create', 'module_code' => 'FIN']);
    $role = Role::factory()->forSchool($school->id)->create(['display_name' => 'Cashier Custom']);

    app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData(
        $role->id,
        [new PermissionGrantData($permission->id, PermissionScope::Section)],
    ));

    $holder = User::factory()->create();
    $holder->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($holder->id, $role->id, $school->id));

    Livewire::actingAs($user)
        ->test(PermissionsExplorer::class, ['school' => $school])
        ->set('selectedPermissionId', $permission->id)
        ->assertSee('Cashier Custom')
        ->assertSee('Section')
        ->assertSee('1');
});

it('does not count a role held by a user in a different school', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForPermissionExplorer($user);
    $otherSchool = School::factory()->create();
    $permission = Permission::factory()->create(['module_code' => 'FIN']);
    $role = Role::factory()->forSchool($school->id)->create();

    app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData(
        $role->id,
        [new PermissionGrantData($permission->id, PermissionScope::Own)],
    ));

    $unrelatedUser = User::factory()->create();
    $unrelatedUser->schools()->attach($otherSchool, ['is_primary' => true, 'status' => 'active']);
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($unrelatedUser->id, $role->id, $otherSchool->id));

    $component = Livewire::actingAs($user)
        ->test(PermissionsExplorer::class, ['school' => $school])
        ->set('selectedPermissionId', $permission->id);

    expect($component->viewData('grants')->first()['holders'])->toBe(0);
});

it('resolves the widest effective scope across a user\'s roles on the "by user" tab (BR-CORE-05-015)', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForPermissionExplorer($user);
    $permission = Permission::factory()->create(['name' => 'academic.result.enter', 'module_code' => 'ACA']);

    $narrowRole = Role::factory()->forSchool($school->id)->create();
    $wideRole = Role::factory()->forSchool($school->id)->create();

    app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData($narrowRole->id, [new PermissionGrantData($permission->id, PermissionScope::Own)]));
    app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData($wideRole->id, [new PermissionGrantData($permission->id, PermissionScope::School)]));

    $subject = User::factory()->create();
    $subject->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($subject->id, $narrowRole->id, $school->id));
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($subject->id, $wideRole->id, $school->id));

    SchoolContext::set($school);

    Livewire::actingAs($user)
        ->test(PermissionsExplorer::class, ['school' => $school])
        ->call('selectMode', 'user')
        ->set('selectedUserId', $subject->id)
        ->assertSee('academic.result.enter')
        ->assertSee('School');
});
