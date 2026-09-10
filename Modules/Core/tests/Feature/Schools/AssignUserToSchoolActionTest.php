<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Schools\AssignUserToSchoolAction;
use Modules\Core\Domain\DataObjects\Schools\AssignUserData;
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
