<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\CreatePeriodStructureAction;
use Modules\Academic\Domain\Actions\CreateTimetableSlotAction;
use Modules\Academic\Domain\Actions\CreateVenueAction;
use Modules\Academic\Domain\Actions\PublishTimetableAction;
use Modules\Academic\Domain\DataObjects\CreatePeriodStructureData;
use Modules\Academic\Domain\DataObjects\CreateTimetableSlotData;
use Modules\Academic\Domain\DataObjects\CreateVenueData;
use Modules\Academic\Domain\DataObjects\PeriodSlotInput;
use Modules\Academic\Domain\DataObjects\PublishTimetableData;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Timetable;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Facilities\Domain\Actions\CreateBookableResourceAction;
use Modules\Facilities\Domain\DataObjects\CreateBookableResourceData;
use Modules\Facilities\Livewire\Calendar\Index as CalendarIndex;
use Modules\Facilities\Livewire\Hire\Index as HireIndex;
use Modules\Facilities\Livewire\Request\Index as RequestIndex;
use Modules\Facilities\Livewire\Resources\Index as ResourcesIndex;
use Modules\Facilities\Livewire\Utilisation\Index as UtilisationIndex;
use Modules\Facilities\Models\ResourceBooking;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Staff;

/**
 * Book H2 OPS-05 admin-UI pass. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, costCentre: CostCentre, account: Account}
 */
function facilitiesAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);

    $costCentre = CostCentre::factory()->for($school)->create();
    $account = Account::factory()->for($school)->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'resource_booking', pattern: 'BKG/{SEQ:6}'));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}'));

    return compact('school', 'year', 'term', 'costCentre', 'account');
}

/**
 * @param  array<string, mixed>  $f
 */
function facilitiesAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $lastDot = strrpos($permissionName, '.');
        $moduleCode = strtoupper(substr($permissionName, 0, strpos($permissionName, '.')));
        $action = substr($permissionName, $lastDot + 1);
        $resource = substr($permissionName, strpos($permissionName, '.') + 1, $lastDot - strpos($permissionName, '.') - 1);
        $resource = $resource !== '' ? $resource : $action;

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => $moduleCode, 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

it('refuses to mount the request screen for a user with no facilities.book grant', function (): void {
    $f = facilitiesAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(RequestIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every facilities screen for a fully-permissioned user', function (): void {
    $f = facilitiesAdminFixture();
    $user = facilitiesAdminUser(
        $f,
        'facilities.manage', 'facilities.view', 'facilities.book', 'facilities.approve',
        'facilities.hire.manage', 'facilities.report.view',
    );

    Livewire::actingAs($user)->test(ResourcesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(CalendarIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(RequestIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(HireIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(UtilisationIndex::class, ['school' => $f['school']])->assertOk();
});

it('refuses an external hire booking that clashes with a published teaching timetable slot (AC-OPS-05-001)', function (): void {
    $f = facilitiesAdminFixture();
    $staff = facilitiesAdminUser($f, 'facilities.manage', 'facilities.book');

    // A weekday so CycleDayResolver never treats it as a non-teaching day.
    $bookingDate = Carbon::now()->startOfDay();
    while ($bookingDate->isWeekend()) {
        $bookingDate->addDay();
    }

    $term = Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create([
        'number' => 2,
        'is_current' => true,
        'starts_on' => $bookingDate->copy()->subDays(10),
        'ends_on' => $bookingDate->copy()->addDays(90),
        'financial_state' => 'open',
    ]);

    $venue = app(CreateVenueAction::class)->execute(new CreateVenueData($f['school']->id, 'HALL1', 'Main Hall Venue', 'hall', 200));
    $resource = app(CreateBookableResourceAction::class)->execute(new CreateBookableResourceData(
        schoolId: $f['school']->id, code: 'HALL', name: 'Main Hall', resourceType: 'hall',
        costCentreId: $f['costCentre']->id, venueId: $venue->id,
    ));

    // A single-cycle-day structure so every teaching day resolves to cycle day 1.
    $structure = app(CreatePeriodStructureAction::class)->execute(new CreatePeriodStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Clash Structure',
        cycleType: 'weekly', cycleDays: 1, dayLabels: ['Day 1'],
        slots: [new PeriodSlotInput(cycleDay: 1, periodNumber: 1, label: 'Period 1', slotType: 'teaching', startsAt: '09:00', endsAt: '10:00', durationMinutes: 60)],
    ));
    $periodSlot = $structure->slots()->where('cycle_day', 1)->where('period_number', 1)->firstOrFail();

    $timetable = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $term->id,
        'structure_id' => $structure->id, 'created_by' => $staff->id,
    ]);

    $subject = Subject::factory()->for($f['school'])->create();
    $classTeacher = Staff::factory()->for($f['school'])->create();

    app(CreateTimetableSlotAction::class)->execute(new CreateTimetableSlotData(
        timetableId: $timetable->id, termId: $term->id, periodSlotId: $periodSlot->id,
        cycleDay: 1, periodNumber: 1, subjectId: $subject->id, staffId: $classTeacher->id, venueId: $venue->id,
    ));

    app(PublishTimetableAction::class)->execute(new PublishTimetableData(
        timetableId: $timetable->id, publishedByUserId: $staff->id,
    ));

    Livewire::actingAs($staff)->test(RequestIndex::class, ['school' => $f['school']])
        ->set('resourceId', $resource->id)
        ->set('bookingType', 'external')
        ->set('purpose', 'Community fair')
        ->set('startsAt', $bookingDate->copy()->setTime(9, 0)->toDateTimeString())
        ->set('endsAt', $bookingDate->copy()->setTime(12, 0)->toDateTimeString())
        ->set('hirerName', 'Local Church')
        ->call('checkAvailability')
        ->assertSet('clashReason', fn (?string $reason): bool => $reason !== null && str_contains($reason, 'Teaching timetable clash'));

    Livewire::actingAs($staff)->test(RequestIndex::class, ['school' => $f['school']])
        ->set('resourceId', $resource->id)
        ->set('bookingType', 'external')
        ->set('purpose', 'Community fair')
        ->set('startsAt', $bookingDate->copy()->setTime(9, 0)->toDateTimeString())
        ->set('endsAt', $bookingDate->copy()->setTime(12, 0)->toDateTimeString())
        ->set('hirerName', 'Local Church')
        ->call('request')
        ->assertOk();

    expect(ResourceBooking::where('resource_id', $resource->id)->count())->toBe(0);
});
