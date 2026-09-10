<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Schools\ToggleSchoolModuleAction;
use Modules\Core\Domain\DataObjects\Schools\ToggleModuleData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolModule;

it('enables a module with no declared dependencies', function (): void {
    $school = School::factory()->create();
    $admin = User::factory()->create();
    SchoolContext::set($school);

    (new ToggleSchoolModuleAction)->execute(new ToggleModuleData(
        schoolId: $school->id,
        moduleCode: 'CORE',
        enable: true,
        actingUserId: $admin->id,
    ));

    $entitlement = SchoolModule::where('school_id', $school->id)->where('module_code', 'CORE')->sole();
    expect($entitlement->is_enabled)->toBeTrue()
        ->and($entitlement->enabled_by)->toBe($admin->id);
});

it('disabling a module preserves its entitlement row rather than deleting it (BR-CORE-02-012)', function (): void {
    $school = School::factory()->create();
    $admin = User::factory()->create();
    SchoolContext::set($school);

    (new ToggleSchoolModuleAction)->execute(new ToggleModuleData($school->id, 'CORE', true, $admin->id));
    (new ToggleSchoolModuleAction)->execute(new ToggleModuleData($school->id, 'CORE', false, $admin->id));

    $entitlement = SchoolModule::where('school_id', $school->id)->where('module_code', 'CORE')->sole();
    expect($entitlement->is_enabled)->toBeFalse();

    (new ToggleSchoolModuleAction)->execute(new ToggleModuleData($school->id, 'CORE', true, $admin->id));
    expect(SchoolModule::where('school_id', $school->id)->where('module_code', 'CORE')->count())->toBe(1);
});

it('refuses to enable a module while a declared dependency is disabled (BR-CORE-02-013)', function (): void {
    config(['core.module_dependencies' => ['BRD' => ['CORE']]]);

    $school = School::factory()->create();
    $admin = User::factory()->create();
    SchoolContext::set($school);

    (new ToggleSchoolModuleAction)->execute(new ToggleModuleData($school->id, 'BRD', true, $admin->id));
})->throws(InvalidStateTransitionException::class);

it('allows enabling once every declared dependency is enabled', function (): void {
    config(['core.module_dependencies' => ['BRD' => ['CORE']]]);

    $school = School::factory()->create();
    $admin = User::factory()->create();
    SchoolContext::set($school);

    (new ToggleSchoolModuleAction)->execute(new ToggleModuleData($school->id, 'CORE', true, $admin->id));
    (new ToggleSchoolModuleAction)->execute(new ToggleModuleData($school->id, 'BRD', true, $admin->id));

    expect(SchoolModule::where('school_id', $school->id)->where('module_code', 'BRD')->sole()->is_enabled)->toBeTrue();
});
