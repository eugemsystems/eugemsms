<?php

use App\Models\User;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\AllocateTeacherAction;
use Modules\People\Domain\Actions\ApproveLeaveRequestAction;
use Modules\People\Domain\Actions\CancelLeaveRequestAction;
use Modules\People\Domain\Actions\CreateEstablishmentPostAction;
use Modules\People\Domain\Actions\CreateLeaveTypeAction;
use Modules\People\Domain\Actions\CreateStaffAction;
use Modules\People\Domain\Actions\CreateStaffContractAction;
use Modules\People\Domain\Actions\FillEstablishmentPostAction;
use Modules\People\Domain\Actions\RejectLeaveRequestAction;
use Modules\People\Domain\Actions\RenewStaffContractAction;
use Modules\People\Domain\Actions\RequestLeaveAction;
use Modules\People\Domain\DataObjects\AllocateTeacherData;
use Modules\People\Domain\DataObjects\ApproveLeaveRequestData;
use Modules\People\Domain\DataObjects\CancelLeaveRequestData;
use Modules\People\Domain\DataObjects\CreateEstablishmentPostData;
use Modules\People\Domain\DataObjects\CreateLeaveTypeData;
use Modules\People\Domain\DataObjects\CreateStaffContractData;
use Modules\People\Domain\DataObjects\CreateStaffData;
use Modules\People\Domain\DataObjects\FillEstablishmentPostData;
use Modules\People\Domain\DataObjects\RejectLeaveRequestData;
use Modules\People\Domain\DataObjects\RenewStaffContractData;
use Modules\People\Domain\DataObjects\RequestLeaveData;
use Modules\People\Domain\Exceptions\ActiveContractExistsException;
use Modules\People\Domain\Exceptions\DuplicateClassTeacherException;
use Modules\People\Domain\Exceptions\EstablishmentCapacityExceededException;
use Modules\People\Domain\Exceptions\LeaveBalanceExceededException;
use Modules\People\Domain\Exceptions\NotATeachingStaffException;
use Modules\People\Domain\Exceptions\WorkloadCeilingExceededException;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffWorkload;

/**
 * @return array<string, mixed>
 */
function ppl04Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'staff', pattern: 'STF/{SEQ:6}',
    ));

    $subject = Subject::factory()->for($school)->create();
    $class = SchoolClass::factory()->for($school)->for($year)->create();

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'user' => $user, 'subject' => $subject, 'class' => $class,
    ];
}

/**
 * @param  array<string, mixed>  $f
 * @param  array<string, mixed>  $overrides
 */
function createTeachingStaff(array $f, array $overrides = []): Staff
{
    return app(CreateStaffAction::class)->execute(new CreateStaffData(
        schoolId: $f['school']->id,
        firstName: $overrides['firstName'] ?? 'Rudo',
        lastName: $overrides['lastName'] ?? 'Chikwava',
        dateOfBirth: now()->subYears(35),
        gender: 'female',
        primaryPhone: '+263771112222',
        staffCategory: 'teaching',
        joinedOn: now()->subYears(2),
        createdByUserId: $f['user']->id,
        isTeaching: $overrides['isTeaching'] ?? true,
        maxWeeklyPeriods: $overrides['maxWeeklyPeriods'] ?? 30,
    ));
}

it('allocates a gapless staff number and makes it immutable (BR-PPL-04-001)', function (): void {
    $f = ppl04Fixture();
    $staff = createTeachingStaff($f);

    expect($staff->staff_number)->not->toBeNull();

    expect(fn () => $staff->update(['staff_number' => 'HACKED/000001']))
        ->toThrow(InvalidStateTransitionException::class);
});

it('refuses a second active contract, then renews one into a new linked contract (BR-PPL-04-002)', function (): void {
    $f = ppl04Fixture();
    $staff = createTeachingStaff($f);

    $first = app(CreateStaffContractAction::class)->execute(new CreateStaffContractData(
        schoolId: $f['school']->id, staffId: $staff->id, contractType: 'permanent',
        startsOn: now()->subYear(), createdByUserId: $f['user']->id,
    ));

    expect(fn () => app(CreateStaffContractAction::class)->execute(new CreateStaffContractData(
        schoolId: $f['school']->id, staffId: $staff->id, contractType: 'fixed_term',
        startsOn: now(), createdByUserId: $f['user']->id,
    )))->toThrow(ActiveContractExistsException::class);

    $renewed = app(RenewStaffContractAction::class)->execute(new RenewStaffContractData(
        contractId: $first->id, contractType: 'permanent', startsOn: now(), renewedByUserId: $f['user']->id,
    ));

    expect($renewed->status)->toBe('active')
        ->and($first->fresh()->status)->toBe('renewed')
        ->and($first->fresh()->renewed_to_contract_id)->toBe($renewed->id);
});

it('fills an establishment post up to capacity, then requires an override reason beyond it (BR-PPL-04-004)', function (): void {
    $f = ppl04Fixture();
    $post = app(CreateEstablishmentPostAction::class)->execute(new CreateEstablishmentPostData(
        schoolId: $f['school']->id, title: 'Senior Teacher — Mathematics', approvedCount: 1,
    ));

    app(FillEstablishmentPostAction::class)->execute(new FillEstablishmentPostData(postId: $post->id));

    expect($post->fresh()->filled_count)->toBe(1);

    expect(fn () => app(FillEstablishmentPostAction::class)->execute(new FillEstablishmentPostData(postId: $post->id)))
        ->toThrow(EstablishmentCapacityExceededException::class);

    $overridden = app(FillEstablishmentPostAction::class)->execute(new FillEstablishmentPostData(
        postId: $post->id, overrideEstablishment: true, overrideReason: 'Temporary double-cover during recruitment',
    ));

    expect($overridden->filled_count)->toBe(2);
});

it('refuses to allocate a non-teaching staff member to a subject (BR-PPL-04-005)', function (): void {
    $f = ppl04Fixture();
    $staff = createTeachingStaff($f, ['isTeaching' => false]);

    expect(fn () => app(AllocateTeacherAction::class)->execute(new AllocateTeacherData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        staffId: $staff->id, subjectId: $f['subject']->id, classId: $f['class']->id,
        weeklyPeriods: 4, allocatedByUserId: $f['user']->id,
    )))->toThrow(NotATeachingStaffException::class);
});

it('refuses a second class teacher for the same class and term (BR-PPL-04-007)', function (): void {
    $f = ppl04Fixture();
    $teacherA = createTeachingStaff($f, ['firstName' => 'A']);
    $teacherB = createTeachingStaff($f, ['firstName' => 'B']);

    app(AllocateTeacherAction::class)->execute(new AllocateTeacherData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        staffId: $teacherA->id, subjectId: $f['subject']->id, classId: $f['class']->id,
        weeklyPeriods: 4, allocatedByUserId: $f['user']->id, isClassTeacher: true,
    ));

    expect(fn () => app(AllocateTeacherAction::class)->execute(new AllocateTeacherData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        staffId: $teacherB->id, subjectId: $f['subject']->id, classId: $f['class']->id,
        weeklyPeriods: 4, allocatedByUserId: $f['user']->id, isClassTeacher: true,
    )))->toThrow(DuplicateClassTeacherException::class);
});

it('lets an overloaded allocation proceed with a live workload recalculation when the ceiling is not enforced (AC-PPL-04-001/002)', function (): void {
    $f = ppl04Fixture();
    $staff = createTeachingStaff($f, ['maxWeeklyPeriods' => 30]);

    app(AllocateTeacherAction::class)->execute(new AllocateTeacherData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        staffId: $staff->id, subjectId: $f['subject']->id, classId: $f['class']->id,
        weeklyPeriods: 28, allocatedByUserId: $f['user']->id,
    ));

    $secondClass = SchoolClass::factory()->for($f['school'])->for($f['year'])->create();

    $allocation = app(AllocateTeacherAction::class)->execute(new AllocateTeacherData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        staffId: $staff->id, subjectId: $f['subject']->id, classId: $secondClass->id,
        weeklyPeriods: 4, allocatedByUserId: $f['user']->id,
    ));

    expect($allocation->id)->not->toBeNull();

    $workload = StaffWorkload::where('staff_id', $staff->id)->where('term_id', $f['term']->id)->firstOrFail();
    expect((int) $workload->teaching_periods)->toBe(32)
        ->and($workload->is_overloaded)->toBeTrue();
});

it('blocks an overloaded allocation when the ceiling is enforced, unless overridden (BR-PPL-04-006)', function (): void {
    $f = ppl04Fixture();
    $staff = createTeachingStaff($f, ['maxWeeklyPeriods' => 10]);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData(
        key: 'staff.enforce_workload_ceiling', scopeType: SettingScope::School, scopeId: $f['school']->id, value: true, setByUserId: $f['user']->id,
    ));

    expect(fn () => app(AllocateTeacherAction::class)->execute(new AllocateTeacherData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        staffId: $staff->id, subjectId: $f['subject']->id, classId: $f['class']->id,
        weeklyPeriods: 12, allocatedByUserId: $f['user']->id,
    )))->toThrow(WorkloadCeilingExceededException::class);

    $allocation = app(AllocateTeacherAction::class)->execute(new AllocateTeacherData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        staffId: $staff->id, subjectId: $f['subject']->id, classId: $f['class']->id,
        weeklyPeriods: 12, allocatedByUserId: $f['user']->id, overrideCeiling: true,
    ));

    expect($allocation->id)->not->toBeNull();
});

it('refuses a leave request beyond the available balance, unless overdraft is approved (AC-PPL-04-003)', function (): void {
    $f = ppl04Fixture();
    $staff = createTeachingStaff($f);
    $leaveType = app(CreateLeaveTypeAction::class)->execute(new CreateLeaveTypeData(
        schoolId: $f['school']->id, code: 'ANN', name: 'Annual Leave', accrualMethod: 'annual', annualEntitlementDays: '8',
    ));

    expect(fn () => app(RequestLeaveAction::class)->execute(new RequestLeaveData(
        schoolId: $f['school']->id, staffId: $staff->id, leaveTypeId: $leaveType->id, academicYearId: $f['year']->id,
        startsOn: now()->addWeek(), endsOn: now()->addWeek()->addDays(9), workingDays: '10',
    )))->toThrow(LeaveBalanceExceededException::class);

    $request = app(RequestLeaveAction::class)->execute(new RequestLeaveData(
        schoolId: $f['school']->id, staffId: $staff->id, leaveTypeId: $leaveType->id, academicYearId: $f['year']->id,
        startsOn: now()->addWeek(), endsOn: now()->addWeek()->addDays(9), workingDays: '10', approveOverdraft: true,
    ));

    expect($request->status)->toBe('pending');
});

it('moves days available → pending → taken on submit and approval, and never double-counts (AC-PPL-04-004)', function (): void {
    $f = ppl04Fixture();
    $staff = createTeachingStaff($f);
    $leaveType = app(CreateLeaveTypeAction::class)->execute(new CreateLeaveTypeData(
        schoolId: $f['school']->id, code: 'ANN', name: 'Annual Leave', accrualMethod: 'annual', annualEntitlementDays: '21',
    ));

    $request = app(RequestLeaveAction::class)->execute(new RequestLeaveData(
        schoolId: $f['school']->id, staffId: $staff->id, leaveTypeId: $leaveType->id, academicYearId: $f['year']->id,
        startsOn: now()->addWeek(), endsOn: now()->addWeek()->addDays(4), workingDays: '5',
    ));

    $balance = LeaveBalance::where('staff_id', $staff->id)->where('leave_type_id', $leaveType->id)->firstOrFail();
    expect((float) $balance->available_days)->toBe(16.0)
        ->and((float) $balance->pending_days)->toBe(5.0);

    app(ApproveLeaveRequestAction::class)->execute(new ApproveLeaveRequestData($request->id, $f['user']->id));

    $balance->refresh();
    expect((float) $balance->pending_days)->toBe(0.0)
        ->and((float) $balance->taken_days)->toBe(5.0)
        ->and((float) $balance->available_days)->toBe(16.0)
        ->and($staff->fresh()->status)->toBe('on_leave');
});

it('restores days to available on rejection and on cancellation of an approved request', function (): void {
    $f = ppl04Fixture();
    $staff = createTeachingStaff($f);
    $leaveType = app(CreateLeaveTypeAction::class)->execute(new CreateLeaveTypeData(
        schoolId: $f['school']->id, code: 'ANN', name: 'Annual Leave', accrualMethod: 'annual', annualEntitlementDays: '21',
    ));

    $rejected = app(RequestLeaveAction::class)->execute(new RequestLeaveData(
        schoolId: $f['school']->id, staffId: $staff->id, leaveTypeId: $leaveType->id, academicYearId: $f['year']->id,
        startsOn: now()->addWeek(), endsOn: now()->addWeek()->addDays(1), workingDays: '2',
    ));
    app(RejectLeaveRequestAction::class)->execute(new RejectLeaveRequestData($rejected->id, $f['user']->id));

    $balance = LeaveBalance::where('staff_id', $staff->id)->where('leave_type_id', $leaveType->id)->firstOrFail();
    expect((float) $balance->available_days)->toBe(21.0)
        ->and((float) $balance->pending_days)->toBe(0.0)
        ->and($rejected->fresh()->status)->toBe('rejected');

    $approved = app(RequestLeaveAction::class)->execute(new RequestLeaveData(
        schoolId: $f['school']->id, staffId: $staff->id, leaveTypeId: $leaveType->id, academicYearId: $f['year']->id,
        startsOn: now()->addWeek(), endsOn: now()->addWeek()->addDays(1), workingDays: '2',
    ));
    app(ApproveLeaveRequestAction::class)->execute(new ApproveLeaveRequestData($approved->id, $f['user']->id));
    app(CancelLeaveRequestAction::class)->execute(new CancelLeaveRequestData($approved->id, $f['user']->id));

    $balance->refresh();
    expect((float) $balance->available_days)->toBe(21.0)
        ->and((float) $balance->taken_days)->toBe(0.0)
        ->and($approved->fresh()->status)->toBe('cancelled')
        ->and($staff->fresh()->status)->toBe('active');
});
