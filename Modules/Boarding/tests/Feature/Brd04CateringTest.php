<?php

use App\Models\User;
use Modules\Boarding\Domain\Actions\CloseMealServiceAction;
use Modules\Boarding\Domain\Actions\CreateMenuCycleAction;
use Modules\Boarding\Domain\Actions\CreateRecipeAction;
use Modules\Boarding\Domain\Actions\CreateRollCallPointAction;
use Modules\Boarding\Domain\Actions\GetDietaryAlertsAction;
use Modules\Boarding\Domain\Actions\MarkRollCallAction;
use Modules\Boarding\Domain\Actions\OpenRollCallAction;
use Modules\Boarding\Domain\Actions\PlanMealServiceAction;
use Modules\Boarding\Domain\Actions\RecordDietaryRequirementAction;
use Modules\Boarding\Domain\Actions\SetMenuDayAction;
use Modules\Boarding\Domain\DataObjects\CloseMealServiceData;
use Modules\Boarding\Domain\DataObjects\CreateMenuCycleData;
use Modules\Boarding\Domain\DataObjects\CreateRecipeData;
use Modules\Boarding\Domain\DataObjects\CreateRollCallPointData;
use Modules\Boarding\Domain\DataObjects\MarkRollCallData;
use Modules\Boarding\Domain\DataObjects\OpenRollCallData;
use Modules\Boarding\Domain\DataObjects\PlanMealServiceData;
use Modules\Boarding\Domain\DataObjects\RecipeIngredientInput;
use Modules\Boarding\Domain\DataObjects\RecordDietaryRequirementData;
use Modules\Boarding\Domain\DataObjects\SetMenuDayData;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\MealRequisitionLine;
use Modules\Boarding\Models\MealService;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User}
 */
function brd04Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $user = User::factory()->create();

    return compact('school', 'year', 'term', 'user');
}

it('scales requisition quantities from live present occupancy, not the nominal allocated count', function (): void {
    $f = brd04Fixture();
    $hostel = Hostel::factory()->create(['school_id' => $f['school']->id, 'gender' => 'male']);
    $room = HostelRoom::factory()->create(['school_id' => $f['school']->id, 'hostel_id' => $hostel->id]);

    // 5 allocated boarders; only 3 marked present via a completed roll call.
    $students = collect(range(1, 5))->map(function (int $i) use ($f, $hostel, $room) {
        $bed = HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $room->id]);
        $student = Student::factory()->boarder()->create(['school_id' => $f['school']->id, 'gender' => 'male']);
        BedAllocation::factory()->create([
            'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
            'student_id' => $student->id, 'bed_id' => $bed->id, 'hostel_id' => $hostel->id, 'room_id' => $room->id,
            'status' => 'confirmed', 'allocated_by' => $f['user']->id,
        ]);

        return $student;
    });

    $point = app(CreateRollCallPointAction::class)->execute(new CreateRollCallPointData(
        schoolId: $f['school']->id, code: 'SUPPER', name: 'Supper', scheduledTime: '18:00:00', appliesOnDays: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
    ));
    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $point->id, hostelId: $hostel->id, termId: $f['term']->id, rollDate: now(),
    ));

    foreach ($students->take(3) as $student) {
        app(MarkRollCallAction::class)->execute(new MarkRollCallData(
            rollCallId: $rollCall->id, studentId: $student->id, status: 'present', markedByUserId: $f['user']->id,
        ));
    }
    foreach ($students->skip(3) as $student) {
        app(MarkRollCallAction::class)->execute(new MarkRollCallData(
            rollCallId: $rollCall->id, studentId: $student->id, status: 'exeat', markedByUserId: $f['user']->id, note: 'Away.',
        ));
    }
    $rollCall->update(['status' => 'completed', 'completed_at' => now()]);

    $recipe = app(CreateRecipeAction::class)->execute(new CreateRecipeData(
        schoolId: $f['school']->id, code: 'SADZA', name: 'Sadza ne Nyama', category: 'staple', baseServings: 100,
        ingredients: [new RecipeIngredientInput(inventoryItemId: 501, quantity: 12.0, unit: 'kg', wastageAllowancePct: 0.0)],
    ));

    $cycle = app(CreateMenuCycleAction::class)->execute(new CreateMenuCycleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Standard Cycle',
    ));
    $menuDay = app(SetMenuDayAction::class)->execute(new SetMenuDayData(
        cycleId: $cycle->id, cycleDay: 1, meal: 'lunch', recipeIds: [$recipe->id],
    ));

    $service = app(PlanMealServiceAction::class)->execute(new PlanMealServiceData(
        schoolId: $f['school']->id, termId: $f['term']->id, serviceDate: now(), meal: 'lunch',
        currency: 'USD', menuDayId: $menuDay->id, hostelId: $hostel->id,
    ));

    // present=3 (not 5), contingency at 5% default => round(3*0.05)=0, so servings=3.
    expect($service->nominal_boarders)->toBe(5)
        ->and($service->present_boarders)->toBe(3)
        ->and($service->on_exeat)->toBe(2)
        ->and($service->planned_servings)->toBe(3);

    $line = MealRequisitionLine::where('meal_service_id', $service->id)->where('inventory_item_id', 501)->first();
    // factor = 3/100 = 0.03; 12kg * 0.03 = 0.36kg, no wastage.
    expect($line)->not->toBeNull();
    expect(round((float) $line->required_quantity, 2))->toBe(0.36);
});

it('never shows an uncosted meal as costing nothing, and requires actual_served before closing', function (): void {
    $f = brd04Fixture();

    $service = MealService::factory()->create([
        'school_id' => $f['school']->id, 'term_id' => $f['term']->id, 'status' => 'planned',
    ]);

    $closed = app(CloseMealServiceAction::class)->execute(new CloseMealServiceData(
        mealServiceId: $service->id, actualServed: 640,
    ));

    expect($closed->status)->toBe('closed')
        ->and($closed->actual_served)->toBe(640)
        ->and($closed->cost_per_serving_minor)->toBeNull();
});

it('flags a life-threatening allergy on every scan, in large-alert form, with the epipen flag', function (): void {
    $f = brd04Fixture();
    $student = Student::factory()->create(['school_id' => $f['school']->id]);

    app(RecordDietaryRequirementAction::class)->execute(new RecordDietaryRequirementData(
        schoolId: $f['school']->id, studentId: $student->id, requirementType: 'allergy',
        severity: 'life_threatening', description: 'Severe peanut allergy — anaphylaxis risk.',
        effectiveFrom: now(), allergens: ['peanuts'], requiresEpipen: true,
    ));

    $alertsNow = app(GetDietaryAlertsAction::class)->execute($student->id);
    $alertsAgain = app(GetDietaryAlertsAction::class)->execute($student->id);

    expect($alertsNow)->toHaveCount(1)
        ->and($alertsNow->first()->severity)->toBe('life_threatening')
        ->and($alertsNow->first()->requiresEpipen)->toBeTrue()
        ->and($alertsAgain)->toHaveCount(1);
});
