<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Boarding\Domain\Actions\CompleteRollCallAction;
use Modules\Boarding\Domain\Actions\MarkRollCallAction;
use Modules\Boarding\Domain\Actions\OpenRollCallAction;
use Modules\Boarding\Domain\DataObjects\CompleteRollCallData;
use Modules\Boarding\Domain\DataObjects\MarkRollCallData;
use Modules\Boarding\Domain\DataObjects\OpenRollCallData;
use Modules\Boarding\Livewire\Catering\Dietary;
use Modules\Boarding\Livewire\Catering\ServicePlan;
use Modules\Boarding\Livewire\Catering\ServingTerminal;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\DietaryRequirement;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\MealService;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book F BRD-04 admin UI — Catering, Menus & Kitchen. Own,
 * distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, hostel: Hostel, user: User}
 */
function cateringAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $hostel = Hostel::factory()->create(['school_id' => $school->id]);

    return ['school' => $school, 'year' => $year, 'term' => $term, 'hostel' => $hostel, 'user' => User::factory()->create()];
}

/**
 * @param  array<string, mixed>  $f
 */
function cateringAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $parts = explode('.', $permissionName);
        $moduleCode = $parts[0];
        $action = array_pop($parts);
        $resource = implode('.', array_slice($parts, 1));

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($moduleCode), 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    if ($grants !== []) {
        app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
            userId: $user->id, schoolId: $f['school']->id, grants: $grants,
        ));
    }

    return $user;
}

it('serves every BRD-04 screen through a real routed request', function (): void {
    $f = cateringAdminFixture();
    $user = cateringAdminUser($f, 'boarding.catering.menu.view', 'boarding.catering.menu.manage', 'boarding.catering.recipe.manage', 'boarding.catering.service.view', 'boarding.catering.serve', 'boarding.catering.dietary.view');

    foreach ([
        'boarding.catering.menu-cycles',
        'boarding.catering.recipes',
        'boarding.catering.service-plan',
        'boarding.catering.serving-terminal',
        'boarding.catering.dietary',
    ] as $routeName) {
        $this->actingAs($user)->get(route($routeName, $f['school']))->assertOk();
    }
});

it('refuses Catering\\ServingTerminal to a user without boarding.catering.serve', function (): void {
    $f = cateringAdminFixture();
    $user = cateringAdminUser($f);

    Livewire::actingAs($user)->test(ServingTerminal::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('plans a meal service from live present occupancy, never from allocated beds (BR-BRD-04-001)', function (): void {
    $f = cateringAdminFixture();
    $room = HostelRoom::factory()->create(['school_id' => $f['school']->id, 'hostel_id' => $f['hostel']->id, 'bed_count' => 2]);
    $bedA = HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $room->id]);
    $bedB = HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $room->id]);

    $studentPresent = Student::factory()->for($f['school'])->boarder()->create();
    $studentMissing = Student::factory()->for($f['school'])->boarder()->create();

    foreach ([[$studentPresent, $bedA], [$studentMissing, $bedB]] as [$student, $bed]) {
        BedAllocation::factory()->create([
            'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
            'student_id' => $student->id, 'bed_id' => $bed->id, 'hostel_id' => $f['hostel']->id, 'room_id' => $room->id,
            'status' => 'confirmed', 'effective_from' => Carbon::now()->subDay(), 'effective_to' => null,
        ]);
    }

    // Both allocated (nominal = 2), but a completed roll call shows only
    // one actually present — servings must scale to that 1, not 2.
    $point = RollCallPoint::factory()->create(['school_id' => $f['school']->id]);
    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $point->id, hostelId: $f['hostel']->id, termId: $f['term']->id, rollDate: Carbon::now(),
    ));
    app(MarkRollCallAction::class)->execute(new MarkRollCallData(rollCallId: $rollCall->id, studentId: $studentPresent->id, status: 'present', markedByUserId: $f['user']->id));
    app(MarkRollCallAction::class)->execute(new MarkRollCallData(rollCallId: $rollCall->id, studentId: $studentMissing->id, status: 'missing', markedByUserId: $f['user']->id));
    app(CompleteRollCallAction::class)->execute(new CompleteRollCallData(rollCallId: $rollCall->id, completedByUserId: $f['user']->id));

    $user = cateringAdminUser($f, 'boarding.catering.service.view', 'boarding.catering.service.manage');

    Livewire::actingAs($user)->test(ServicePlan::class, ['school' => $f['school']])
        ->set('meal', 'lunch')
        ->call('plan');

    $service = MealService::where('school_id', $f['school']->id)->where('meal', 'lunch')->first();
    expect($service)->not->toBeNull()
        ->and($service->nominal_boarders)->toBe(2)
        ->and($service->present_boarders)->toBe(1);
});

it('surfaces a life-threatening dietary alert, unverified until a nurse verifies it (BR-BRD-04-009/010)', function (): void {
    $f = cateringAdminFixture();
    $student = Student::factory()->for($f['school'])->boarder()->create();
    $user = cateringAdminUser($f, 'boarding.catering.dietary.view', 'boarding.catering.dietary.manage');

    Livewire::actingAs($user)->test(Dietary::class, ['school' => $f['school']])
        ->set('studentId', $student->id)
        ->set('requirementType', 'allergy')
        ->set('severity', 'life_threatening')
        ->set('description', 'Severe peanut allergy.')
        ->set('requiresEpipen', true)
        ->call('record');

    $requirement = DietaryRequirement::where('student_id', $student->id)->first();
    expect($requirement)->not->toBeNull()
        ->and($requirement->severity)->toBe('life_threatening')
        ->and($requirement->verified_by_nurse)->toBeFalse();

    Livewire::actingAs($user)->test(Dietary::class, ['school' => $f['school']])
        ->call('verify', $requirement->id);

    expect($requirement->refresh()->verified_by_nurse)->toBeTrue();
});
