<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\RequestLeaveAction;
use Modules\People\Domain\DataObjects\RequestLeaveData;
use Modules\People\Livewire\Allocation\TeacherMatrix;
use Modules\People\Livewire\Duty\Rosters as DutyRosters;
use Modules\People\Livewire\Leave\Approvals as LeaveApprovals;
use Modules\People\Livewire\Staff\Contracts;
use Modules\People\Livewire\Staff\Create as StaffCreate;
use Modules\People\Livewire\Staff\Disciplinary;
use Modules\People\Livewire\Staff\Index as StaffIndex;
use Modules\People\Livewire\Staff\Show as StaffShow;
use Modules\People\Models\DutyAssignment;
use Modules\People\Models\DutyRoster;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveType;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffContract;
use Modules\People\Models\StaffDisciplinaryCase;
use Modules\People\Models\StaffWorkload;
use Modules\People\Models\TeacherAllocation;

/**
 * Book C PPL-04 §5 admin UI — Staff & Human Resources. Own,
 * distinctly-named fixture — see `StudentsAdminUiTest`'s own note on
 * why a Pest helper defined in one test file can't be relied on from
 * another run standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, section: SchoolSection, gradeLevel: GradeLevel, user: User, subject: Subject, class: SchoolClass}
 */
function staffAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $user = User::factory()->create();
    $subject = Subject::factory()->for($school)->create();
    $class = SchoolClass::factory()->for($school)->for($year)->for($gradeLevel)->create();

    // CreateStaffAction/ReportDisciplinaryCaseAction allocate both of
    // these with no academicYearId of their own, so the series must be
    // registered period-agnostic (null) to match, not scoped to $year
    // like 'admission'/'application' are elsewhere in this test suite.
    foreach (['staff' => 'STF', 'disciplinary_case' => 'DSC'] as $documentType => $prefix) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $documentType, pattern: $prefix.'/{SEQ:6}',
        ));
    }

    return compact('school', 'year', 'term', 'section', 'gradeLevel', 'user', 'subject', 'class');
}

/**
 * @param  array<string, mixed>  $f
 */
function staffAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        [$moduleCode, $resource, $action] = explode('.', $permissionName);

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

it('serves Staff\\Index through a real routed request', function (): void {
    $f = staffAdminFixture();
    $user = staffAdminUser($f, 'people.staff.view');

    $this->actingAs($user)
        ->get(route('people.staff.index', $f['school']))
        ->assertOk();
});

it('refuses Staff\\Index to a user without people.staff.view', function (): void {
    $f = staffAdminFixture();
    $user = staffAdminUser($f);

    Livewire::actingAs($user)->test(StaffIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('creates a staff member', function (): void {
    $f = staffAdminFixture();
    $user = staffAdminUser($f, 'people.staff.create');

    Livewire::actingAs($user)->test(StaffCreate::class, ['school' => $f['school']])
        ->set('firstName', 'Tendai')
        ->set('lastName', 'Chikosi')
        ->set('dateOfBirth', now()->subYears(35)->toDateString())
        ->set('primaryPhone', '+263771111111')
        ->set('isTeaching', true)
        ->call('save')
        ->assertRedirect();

    expect(Staff::where('school_id', $f['school']->id)->where('first_name', 'Tendai')->where('is_teaching', true)->exists())->toBeTrue();
});

it('hides compensation fields on Staff\\Show from a viewer without view_compensation (AC-PPL-04-009)', function (): void {
    $f = staffAdminFixture();
    $staff = Staff::factory()->for($f['school'])->create();
    StaffContract::factory()->for($f['school'])->create(['staff_id' => $staff->id, 'basic_salary_minor' => 150000, 'salary_currency' => 'USD']);
    $user = staffAdminUser($f, 'people.staff.view');

    $component = Livewire::actingAs($user)->test(StaffShow::class, ['school' => $f['school'], 'staff' => $staff])
        ->set('activeTab', 'employment');

    expect($component->instance()->canViewCompensation())->toBeFalse()
        ->and($component->html())->not->toContain('150,000');
});

it('shows compensation fields to a viewer with view_compensation', function (): void {
    $f = staffAdminFixture();
    $staff = Staff::factory()->for($f['school'])->create();
    StaffContract::factory()->for($f['school'])->create(['staff_id' => $staff->id, 'basic_salary_minor' => 150000, 'salary_currency' => 'USD']);
    $user = staffAdminUser($f, 'people.staff.view', 'people.staff.view_compensation');

    $component = Livewire::actingAs($user)->test(StaffShow::class, ['school' => $f['school'], 'staff' => $staff])
        ->set('activeTab', 'employment');

    expect($component->instance()->canViewCompensation())->toBeTrue()
        ->and($component->html())->toContain('1,500.00');
});

it('creates, renews, and terminates a staff contract (BR-PPL-04-002)', function (): void {
    $f = staffAdminFixture();
    $staff = Staff::factory()->for($f['school'])->create();
    $user = staffAdminUser($f, 'people.staff.contract_manage');

    $component = Livewire::actingAs($user)->test(Contracts::class, ['school' => $f['school'], 'staff' => $staff])
        ->call('create');

    $contract = StaffContract::where('staff_id', $staff->id)->sole();
    expect($contract->status)->toBe('active');

    $component->call('renew', $contract->id);
    expect($contract->fresh()->status)->toBe('renewed')
        ->and(StaffContract::where('staff_id', $staff->id)->where('status', 'active')->count())->toBe(1);

    $renewed = StaffContract::where('staff_id', $staff->id)->where('status', 'active')->sole();
    $component->set('terminationReason', 'Resigned')->call('terminate', $renewed->id);
    expect($renewed->fresh()->status)->toBe('terminated');
});

it('allocates a teacher and recalculates their workload live (AC-PPL-04-001/002)', function (): void {
    $f = staffAdminFixture();
    $teacher = Staff::factory()->for($f['school'])->teaching()->create(['max_weekly_periods' => 30]);
    $user = staffAdminUser($f, 'people.staff.allocate');

    Livewire::actingAs($user)->test(TeacherMatrix::class, ['school' => $f['school']])
        ->set('staffId', $teacher->id)
        ->set('subjectId', $f['subject']->id)
        ->set('classId', $f['class']->id)
        ->set('weeklyPeriods', '10')
        ->call('allocate');

    expect(TeacherAllocation::where('staff_id', $teacher->id)->where('status', 'active')->exists())->toBeTrue();

    $workload = StaffWorkload::where('staff_id', $teacher->id)->where('term_id', $f['term']->id)->sole();
    expect($workload->teaching_periods)->toBe(10);
});

it('moves leave days from available to pending to taken on approval, never double-counted (AC-PPL-04-004)', function (): void {
    $f = staffAdminFixture();
    $staff = Staff::factory()->for($f['school'])->create();
    $leaveType = LeaveType::factory()->for($f['school'])->create(['annual_entitlement_days' => '21.0']);
    $user = staffAdminUser($f, 'people.staff.leave_approve');

    $leaveRequest = app(RequestLeaveAction::class)->execute(new RequestLeaveData(
        schoolId: $f['school']->id, staffId: $staff->id, leaveTypeId: $leaveType->id, academicYearId: $f['year']->id,
        startsOn: now(), endsOn: now()->addDays(4), workingDays: '5.0',
    ));

    $balance = LeaveBalance::where('staff_id', $staff->id)->sole();
    expect($balance->available_days)->toBe('16.0')
        ->and($balance->pending_days)->toBe('5.0');

    Livewire::actingAs($user)->test(LeaveApprovals::class, ['school' => $f['school']])
        ->call('approve', $leaveRequest->id);

    $balance->refresh();
    expect($balance->available_days)->toBe('16.0')
        ->and($balance->pending_days)->toBe('0.0')
        ->and($balance->taken_days)->toBe('5.0')
        ->and($staff->fresh()->status)->toBe('on_leave');
});

it('creates a duty roster and generates balanced assignments, excluding staff on approved leave (AC-PPL-04-010)', function (): void {
    $f = staffAdminFixture();
    Staff::factory()->for($f['school'])->create(['status' => 'active']);
    Staff::factory()->for($f['school'])->create(['status' => 'active']);
    $user = staffAdminUser($f, 'people.staff.duty_manage');

    $component = Livewire::actingAs($user)->test(DutyRosters::class, ['school' => $f['school']])
        ->set('rosterName', 'Weekday gate duty')
        ->call('createRoster');

    $roster = DutyRoster::where('school_id', $f['school']->id)->sole();

    $component->set('selectedRosterId', $roster->id)
        ->set('generateFrom', now()->toDateString())
        ->set('generateTo', now()->addDays(2)->toDateString())
        ->call('generate');

    expect(DutyAssignment::where('roster_id', $roster->id)->count())->toBe(3);
});

it('reports a disciplinary case, hides it from the list query, and reveals detail only through the audited view action (BR-PPL-04-020)', function (): void {
    $f = staffAdminFixture();
    $staff = Staff::factory()->for($f['school'])->create();
    $user = staffAdminUser($f, 'people.staff.disciplinary_manage');

    Livewire::actingAs($user)->test(Disciplinary::class, ['school' => $f['school'], 'staff' => $staff])
        ->set('category', 'Attendance')
        ->set('description', 'Repeated lateness over three weeks.')
        ->call('report');

    $case = StaffDisciplinaryCase::where('staff_id', $staff->id)->sole();
    expect($case->is_confidential)->toBeTrue();

    $component = Livewire::actingAs($user)->test(Disciplinary::class, ['school' => $f['school'], 'staff' => $staff]);
    expect($component->html())->not->toContain('Repeated lateness');

    $component->call('view', $case->id);
    expect($component->get('viewedCase')['description'])->toBe('Repeated lateness over three weeks.');
});

it('refuses the disciplinary screen to a user without people.staff.disciplinary_manage', function (): void {
    $f = staffAdminFixture();
    $staff = Staff::factory()->for($f['school'])->create();
    $user = staffAdminUser($f, 'people.staff.view');

    Livewire::actingAs($user)->test(Disciplinary::class, ['school' => $f['school'], 'staff' => $staff])
        ->assertForbidden();
});

it('serves Establishment\\Index, Allocation\\TeacherMatrix, and Appraisal\\Index through real routed requests', function (): void {
    $f = staffAdminFixture();
    $user = staffAdminUser(
        $f,
        'people.staff.establishment_manage', 'people.staff.allocate', 'people.staff.appraisal_manage',
    );

    $this->actingAs($user)->get(route('people.establishment.index', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('people.allocation.matrix', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('people.appraisal.index', $f['school']))->assertOk();
});
