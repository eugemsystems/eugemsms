<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\Actions\Schools\AssignUserToSchoolAction;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\DataObjects\Schools\AssignUserData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

it('assigns a user to a school', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $admin = User::factory()->create();

    (new AssignUserToSchoolAction)->execute(new AssignUserData(
        schoolId: $school->id,
        userId: $user->id,
        assignedByUserId: $admin->id,
    ));

    expect($user->isAssignedToSchool($school->id))->toBeTrue();
});

it('keeps exactly one primary school per user (BR-CORE-02-007)', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $user = User::factory()->create();
    $admin = User::factory()->create();

    (new AssignUserToSchoolAction)->execute(new AssignUserData($schoolA->id, $user->id, $admin->id, isPrimary: true));
    (new AssignUserToSchoolAction)->execute(new AssignUserData($schoolB->id, $user->id, $admin->id, isPrimary: true));

    $user->refresh();
    expect($user->primarySchool()?->id)->toBe($schoolB->id);

    $primaryCount = $user->schools()->wherePivot('is_primary', true)->count();
    expect($primaryCount)->toBe(1);
});

it('reassigning a user to the same school updates rather than duplicates the pivot row', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $admin = User::factory()->create();

    (new AssignUserToSchoolAction)->execute(new AssignUserData($school->id, $user->id, $admin->id));
    (new AssignUserToSchoolAction)->execute(new AssignUserData($school->id, $user->id, $admin->id, isPrimary: true));

    expect($user->schools()->count())->toBe(1)
        ->and($user->primarySchool()?->id)->toBe($school->id);
});

it('carries a Super Admin\'s role to a newly assigned school (2026-09-13 gap fix)', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $superAdmin = User::factory()->create();
    $admin = User::factory()->create();
    $role = Role::factory()->create(['name' => 'super_admin', 'school_id' => null]);

    app(AssignRoleAction::class)->execute(new RoleAssignmentData($superAdmin->id, $role->id, $schoolA->id));

    (new AssignUserToSchoolAction)->execute(new AssignUserData($schoolB->id, $superAdmin->id, $admin->id));

    SchoolContext::set($schoolB);
    expect($superAdmin->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('does not grant super_admin to an ordinary user newly assigned to a school', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $ordinaryUser = User::factory()->create();
    $admin = User::factory()->create();
    Role::factory()->create(['name' => 'super_admin', 'school_id' => null]);

    (new AssignUserToSchoolAction)->execute(new AssignUserData($schoolB->id, $ordinaryUser->id, $admin->id));

    SchoolContext::set($schoolB);
    expect($ordinaryUser->fresh()->hasRole('super_admin'))->toBeFalse();
});
