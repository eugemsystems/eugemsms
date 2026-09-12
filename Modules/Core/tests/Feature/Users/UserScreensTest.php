<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Users\Form;
use Modules\Core\Livewire\Users\Index;
use Modules\Core\Livewire\Users\Show;
use Modules\Core\Models\LoginAttempt;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\UserAccountLink;
use Spatie\Permission\PermissionRegistrar;

/**
 * `core.role.update` is now enforced on `Show::assignRole()`/
 * `removeRole()` (2026-09-12) — grants it as a direct permission for the
 * acting admin in the given school.
 */
function grantRoleUpdatePermission(User $admin, School $school): void
{
    $permission = Permission::firstOrCreate(
        ['name' => 'core.role.update'],
        ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'role', 'action' => 'update'],
    );

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $admin->id,
        schoolId: $school->id,
        grants: [new PermissionGrantData($permission->id, PermissionScope::School)],
    ));
}

// `users.php` is deliberately not yet wired into CoreServiceProvider (a
// concurrent change registers it there), but every view under test here
// renders route()/redirectRoute() calls against those names — register
// the same route definitions the real file declares so this suite is
// self-contained regardless of registration order. Must happen inside a
// hook (not at file-load time): the Route facade has no root yet while
// PHPUnit is still collecting test files.
beforeEach(function (): void {
    if (! Route::has('users.index')) {
        require base_path('Modules/Core/routes/users.php');
    }
});

it('lists only users belonging to the current admin\'s tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $sameTenantUser = User::factory()->create(['tenant_id' => $tenant->id, 'first_name' => 'Tendai', 'last_name' => 'Moyo']);
    $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'first_name' => 'Foreign', 'last_name' => 'User']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Tendai')
        ->assertDontSee('Foreign');

    expect($sameTenantUser->id)->not->toBeNull()->and($otherTenantUser->id)->not->toBeNull();
});

it('deactivates a user from the index screen', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('deactivate', $target->id);

    expect($target->fresh()->status->value)->toBe('inactive');
});

it('does not deactivate a user belonging to a different tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $otherTenant->id, 'status' => 'active']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('deactivate', $target->id);

    expect($target->fresh()->status->value)->toBe('active');
});

it('creates a user via the form', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('firstName', 'Rudo')
        ->set('lastName', 'Chuma')
        ->set('email', 'rudo@example.com')
        ->set('userType', 'staff')
        ->call('save')
        ->assertHasNoErrors();

    $created = User::where('email', 'rudo@example.com')->sole();
    expect($created->tenant_id)->toBe($tenant->id)
        ->and($created->first_name)->toBe('Rudo');
});

it('creates a user and assigns a role in one step from the form', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $school = School::factory()->create();
    $admin->schools()->attach($school->id, ['status' => 'active']);
    $role = Role::factory()->forSchool($school->id)->create(['display_name' => 'Class Teacher']);
    grantRoleUpdatePermission($admin, $school);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('firstName', 'Rudo')
        ->set('lastName', 'Chuma')
        ->set('email', 'rudo-role@example.com')
        ->set('userType', 'staff')
        ->set('roleSchoolId', $school->id)
        ->set('roleId', $role->id)
        ->call('save')
        ->assertHasNoErrors();

    $created = User::where('email', 'rudo-role@example.com')->sole();

    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
    expect($created->fresh()->hasRole($role))->toBeTrue();
});

it('refuses to assign a role at creation without core.role.update in that school', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $school = School::factory()->create();
    $admin->schools()->attach($school->id, ['status' => 'active']);
    $role = Role::factory()->forSchool($school->id)->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('firstName', 'Rudo')
        ->set('lastName', 'Chuma')
        ->set('email', 'rudo-forbidden@example.com')
        ->set('userType', 'staff')
        ->set('roleSchoolId', $school->id)
        ->set('roleId', $role->id)
        ->call('save')
        ->assertForbidden();

    expect(User::where('email', 'rudo-forbidden@example.com')->exists())->toBeFalse();
});

it('toasts a domain error instead of crashing when the form would leave a user with no identity', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('firstName', 'No')
        ->set('lastName', 'Identity')
        ->call('save')
        ->assertDispatched('toast', variant: 'danger');

    expect(User::where('first_name', 'No')->exists())->toBeFalse();
});

it('edits an existing user via the form', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $tenant->id, 'first_name' => 'Old', 'last_name' => 'Name', 'email' => 'old@example.com', 'user_type' => 'staff']);

    Livewire::actingAs($admin)
        ->test(Form::class, ['user' => $target])
        ->assertSet('firstName', 'Old')
        ->set('firstName', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($target->fresh()->first_name)->toBe('New');
});

it('refuses to open the edit form for a user in a different tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $otherTenant->id]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['user' => $target])
        ->assertForbidden();
});

it('shows a user\'s identity, roles per school, devices, login history, and linked records', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $tenant->id, 'first_name' => 'Tendai', 'last_name' => 'Moyo']);

    $schoolA = School::factory()->create(['name' => 'Alpha High']);
    $schoolB = School::factory()->create(['name' => 'Beta High']);
    $roleA = Role::factory()->forSchool($schoolA->id)->create(['display_name' => 'Teacher']);
    $roleB = Role::factory()->forSchool($schoolB->id)->create(['display_name' => 'Bursar']);
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($target->id, $roleA->id, $schoolA->id));
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($target->id, $roleB->id, $schoolB->id));

    $target->createToken('My Phone');

    LoginAttempt::create([
        'identifier' => (string) $target->email,
        'user_id' => $target->id,
        'guard' => 'web',
        'was_successful' => true,
        'attempted_at' => now(),
    ]);

    UserAccountLink::withoutGlobalScopes()->create([
        'school_id' => $schoolA->id,
        'user_id' => $target->id,
        'linked_type' => 'staff',
        'linked_id' => 1,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['user' => $target])
        ->assertSee('Tendai')
        ->assertSee('Alpha High')
        ->assertSee('Teacher')
        ->assertSee('Beta High')
        ->assertSee('Bursar')
        ->assertSee('My Phone');
});

it('refuses to show a user in a different tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $otherTenant->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['user' => $target])
        ->assertForbidden();
});

it('resets a user\'s password from the show screen (forces a change on next login)', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $tenant->id, 'must_change_password' => false]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['user' => $target])
        ->call('resetPassword')
        ->assertDispatched('toast');

    expect($target->fresh()->must_change_password)->toBeTrue();
});

it('revokes a device from the show screen', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $tenant->id]);
    $token = $target->createToken('Tablet');

    Livewire::actingAs($admin)
        ->test(Show::class, ['user' => $target])
        ->call('revokeToken', $token->accessToken->id);

    expect($token->accessToken->fresh()->revoked_at)->not->toBeNull();
});

it('deactivates a user from the show screen', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['user' => $target])
        ->call('deactivate')
        ->assertDispatched('toast');

    expect($target->fresh()->status->value)->toBe('inactive');
});

it('assigns a role to a user in one of the acting admin\'s own schools', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $tenant->id]);
    $school = School::factory()->create();
    $admin->schools()->attach($school->id, ['status' => 'active']);
    $role = Role::factory()->forSchool($school->id)->create(['display_name' => 'Class Teacher']);
    grantRoleUpdatePermission($admin, $school);

    Livewire::actingAs($admin)
        ->test(Show::class, ['user' => $target])
        ->call('openAssignRoleModal')
        ->set('assignRoleSchoolId', $school->id)
        ->set('assignRoleId', $role->id)
        ->call('assignRole')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect($target->hasRole($role))->toBeFalse(); // ambient team differs from the assignment's school outside the request

    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
    expect($target->fresh()->hasRole($role))->toBeTrue();
});

it('does not offer a school the acting admin has no access to when assigning a role', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $tenant->id]);
    $inaccessibleSchool = School::factory()->create(['name' => 'Off Limits School']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['user' => $target])
        ->assertDontSee('Off Limits School');
});

it('removes a role from a user from the show screen', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $tenant->id]);
    $school = School::factory()->create();
    $role = Role::factory()->forSchool($school->id)->create();
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($target->id, $role->id, $school->id));
    grantRoleUpdatePermission($admin, $school);

    Livewire::actingAs($admin)
        ->test(Show::class, ['user' => $target])
        ->call('removeRole', $role->id, $school->id)
        ->assertDispatched('toast');

    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
    expect($target->fresh()->hasRole($role))->toBeFalse();
});
