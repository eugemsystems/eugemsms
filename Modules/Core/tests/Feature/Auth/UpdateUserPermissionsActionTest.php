<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\UserPermissionScope;
use Spatie\Permission\PermissionRegistrar;

it('replaces a user\'s whole direct-grant set for the given school in one call', function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();
    $view = Permission::factory()->create(['name' => 'core.user.view']);
    $create = Permission::factory()->create(['name' => 'core.user.create']);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: [new PermissionGrantData($view->id, PermissionScope::Own)],
    ));

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: [new PermissionGrantData($create->id, PermissionScope::School)],
    ));

    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
    $fresh = $user->fresh();

    expect($fresh->hasPermissionTo($view))->toBeFalse()
        ->and($fresh->hasPermissionTo($create))->toBeTrue()
        ->and(UserPermissionScope::where('user_id', $user->id)->count())->toBe(1)
        ->and(UserPermissionScope::where('user_id', $user->id)->sole()->scope)->toBe(PermissionScope::School);
});

it('scopes the grant to the school given on the DTO, not whatever school happens to be ambient', function (): void {
    $user = User::factory()->create();
    $targetSchool = School::factory()->create();
    $otherSchool = School::factory()->create();
    $permission = Permission::factory()->create();

    app(PermissionRegistrar::class)->setPermissionsTeamId($otherSchool->id);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $targetSchool->id,
        grants: [new PermissionGrantData($permission->id, PermissionScope::Own)],
    ));

    app(PermissionRegistrar::class)->setPermissionsTeamId($otherSchool->id);
    expect($user->fresh()->hasPermissionTo($permission))->toBeFalse();

    app(PermissionRegistrar::class)->setPermissionsTeamId($targetSchool->id);
    expect($user->fresh()->hasPermissionTo($permission))->toBeTrue();

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($targetSchool->id);
});

it('restores whatever team id was ambient before it ran', function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();
    $permission = Permission::factory()->create();

    app(PermissionRegistrar::class)->setPermissionsTeamId(999);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: [new PermissionGrantData($permission->id, PermissionScope::Own)],
    ));

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe(999);
});
