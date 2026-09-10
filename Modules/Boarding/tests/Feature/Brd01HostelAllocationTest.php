<?php

use App\Models\User;
use Modules\Boarding\Domain\Actions\AllocateBedAction;
use Modules\Boarding\Domain\Actions\ApproveHostelDamageChargeAction;
use Modules\Boarding\Domain\Actions\ConfirmBedAllocationAction;
use Modules\Boarding\Domain\Actions\DisputeHostelDamageChargeAction;
use Modules\Boarding\Domain\Actions\MarkRoomOutOfServiceAction;
use Modules\Boarding\Domain\Actions\ReportHostelDamageAction;
use Modules\Boarding\Domain\Actions\RunBulkAllocationAction;
use Modules\Boarding\Domain\DataObjects\AllocateBedData;
use Modules\Boarding\Domain\DataObjects\ApproveHostelDamageChargeData;
use Modules\Boarding\Domain\DataObjects\ConfirmBedAllocationData;
use Modules\Boarding\Domain\DataObjects\DisputeHostelDamageChargeData;
use Modules\Boarding\Domain\DataObjects\MarkRoomOutOfServiceData;
use Modules\Boarding\Domain\DataObjects\ReportHostelDamageData;
use Modules\Boarding\Domain\DataObjects\RunBulkAllocationData;
use Modules\Boarding\Domain\Exceptions\GenderMismatchException;
use Modules\Boarding\Domain\Exceptions\RoomOccupiedException;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\LearnerIncompatibility;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AdHocCharge;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Domain\Events\LearnerResidencyChanged;
use Modules\People\Models\Student;
use Modules\People\Models\StudentAttributeChange;

/**
 * @return array{school: School, year: AcademicYear, term: Term, gradeLevel: GradeLevel, user: User}
 */
function brd01Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $user = User::factory()->create();

    return compact('school', 'year', 'term', 'gradeLevel', 'user');
}

/**
 * @param  array<string, mixed>  $f
 */
function brd01Hostel(array $f, string $gender, string $code): Hostel
{
    return Hostel::factory()->create(['school_id' => $f['school']->id, 'gender' => $gender, 'code' => $code]);
}

/**
 * @param  array<string, mixed>  $f
 */
function brd01RoomWithBeds(array $f, Hostel $hostel, int $bedCount = 4): HostelRoom
{
    $room = HostelRoom::factory()->create([
        'school_id' => $f['school']->id, 'hostel_id' => $hostel->id, 'bed_count' => $bedCount,
    ]);

    for ($i = 1; $i <= $bedCount; $i++) {
        HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $room->id, 'bed_number' => "B{$i}"]);
    }

    return $room;
}

/**
 * @param  array<string, mixed>  $f
 */
function brd01Student(array $f, string $gender, string $firstName): Student
{
    return Student::factory()->boarder()->create([
        'school_id' => $f['school']->id, 'gender' => $gender, 'grade_level_id' => $f['gradeLevel']->id, 'first_name' => $firstName,
    ]);
}

it('refuses to allocate a bed across gender at the Action layer', function (): void {
    $f = brd01Fixture();
    $girlsHostel = brd01Hostel($f, 'female', 'GIR');
    brd01RoomWithBeds($f, $girlsHostel);
    $boy = brd01Student($f, 'male', 'Tino');

    expect(fn () => app(AllocateBedAction::class)->execute(new AllocateBedData(
        studentId: $boy->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        effectiveFrom: now(), allocatedByUserId: $f['user']->id,
        candidateHostelIds: [$girlsHostel->id],
    )))->toThrow(GenderMismatchException::class);
});

it('never places two incompatible learners in the same room', function (): void {
    $f = brd01Fixture();
    $hostel = brd01Hostel($f, 'male', 'BOY');
    brd01RoomWithBeds($f, $hostel, 1);
    brd01RoomWithBeds($f, $hostel, 1);
    $learnerA = brd01Student($f, 'male', 'A');
    $learnerB = brd01Student($f, 'male', 'B');

    LearnerIncompatibility::factory()->create([
        'school_id' => $f['school']->id, 'student_a_id' => $learnerA->id, 'student_b_id' => $learnerB->id,
        'scope' => 'room', 'raised_by' => $f['user']->id,
    ]);

    $allocationA = app(AllocateBedAction::class)->execute(new AllocateBedData(
        studentId: $learnerA->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        effectiveFrom: now(), allocatedByUserId: $f['user']->id, asDraft: false,
    ));

    $allocationB = app(AllocateBedAction::class)->execute(new AllocateBedData(
        studentId: $learnerB->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        effectiveFrom: now(), allocatedByUserId: $f['user']->id, asDraft: false,
    ));

    expect($allocationA->room_id)->not->toBe($allocationB->room_id);
});

it('ends a bed allocation and re-evaluates the waiting list on a residency change to DAY', function (): void {
    $f = brd01Fixture();
    $hostel = brd01Hostel($f, 'female', 'GIR');
    brd01RoomWithBeds($f, $hostel);
    $learner = brd01Student($f, 'female', 'Rue');

    $allocation = app(AllocateBedAction::class)->execute(new AllocateBedData(
        studentId: $learner->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        effectiveFrom: now()->subMonth(), allocatedByUserId: $f['user']->id, asDraft: false,
    ));

    $change = StudentAttributeChange::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $learner->id, 'academic_year_id' => $f['year']->id,
        'term_id' => $f['term']->id, 'attribute' => 'residency', 'old_value' => 'BOARDER', 'new_value' => 'DAY',
        'effective_from' => now()->toDateString(), 'changed_by' => $f['user']->id,
    ]);

    event(new LearnerResidencyChanged($learner, $change));

    $allocation->refresh();
    expect($allocation->status)->toBe('ended')
        ->and($allocation->effective_to)->not->toBeNull();
});

it('produces drafts only from bulk allocation, listing every unplaced learner', function (): void {
    $f = brd01Fixture();
    $hostel = brd01Hostel($f, 'male', 'BOY');
    brd01RoomWithBeds($f, $hostel, 1);
    $placeable = brd01Student($f, 'male', 'Placeable');
    $unplaceable = brd01Student($f, 'male', 'Unplaceable');

    $outcomes = app(RunBulkAllocationAction::class)->execute(new RunBulkAllocationData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        studentIds: [$placeable->id, $unplaceable->id], effectiveFrom: now(), allocatedByUserId: $f['user']->id,
    ));

    $placedOutcome = $outcomes->firstWhere('studentId', $placeable->id);
    $unplacedOutcome = $outcomes->firstWhere('studentId', $unplaceable->id);

    expect($placedOutcome->isPlaced())->toBeTrue()
        ->and($unplacedOutcome->isPlaced())->toBeFalse()
        ->and($unplacedOutcome->blockingReason)->not->toBeNull()
        ->and(BedAllocation::where('student_id', $placeable->id)->first()?->status)->toBe('draft')
        ->and(BedAllocation::where('student_id', $unplaceable->id)->exists())->toBeFalse();
});

it('confirms a draft allocation only through an explicit human step', function (): void {
    $f = brd01Fixture();
    $hostel = brd01Hostel($f, 'male', 'BOY');
    brd01RoomWithBeds($f, $hostel);
    $learner = brd01Student($f, 'male', 'Draftee');

    $allocation = app(AllocateBedAction::class)->execute(new AllocateBedData(
        studentId: $learner->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        effectiveFrom: now(), allocatedByUserId: $f['user']->id, asDraft: true,
    ));

    expect($allocation->status)->toBe('draft');

    $confirmed = app(ConfirmBedAllocationAction::class)->execute(new ConfirmBedAllocationData(
        allocationId: $allocation->id, confirmedByUserId: $f['user']->id,
    ));

    expect($confirmed->status)->toBe('confirmed');
});

it('refuses to mark an occupied room out of service', function (): void {
    $f = brd01Fixture();
    $hostel = brd01Hostel($f, 'male', 'BOY');
    $room = brd01RoomWithBeds($f, $hostel);
    $learner = brd01Student($f, 'male', 'Occupant');

    app(AllocateBedAction::class)->execute(new AllocateBedData(
        studentId: $learner->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        effectiveFrom: now(), allocatedByUserId: $f['user']->id, asDraft: false,
    ));

    expect(fn () => app(MarkRoomOutOfServiceAction::class)->execute(new MarkRoomOutOfServiceData(
        roomId: $room->id, reason: 'Roof leak',
    )))->toThrow(RoomOccupiedException::class);
});

it('splits a shared-room damage charge exactly across every liable learner and halts on dispute', function (): void {
    $f = brd01Fixture();
    $hostel = brd01Hostel($f, 'male', 'BOY');
    $component = FeeComponent::factory()->create([
        'school_id' => $f['school']->id,
        'code' => 'BRD-DMG',
        'income_account_id' => Account::factory()->for($f['school'])->income()->create()->id,
        'debtor_account_id' => Account::factory()->for($f['school'])->controlAccount('student')->create()->id,
    ]);

    $students = collect(range(1, 6))->map(fn (int $i) => brd01Student($f, 'male', "Occupant{$i}"));

    $damage = app(ReportHostelDamageAction::class)->execute(new ReportHostelDamageData(
        schoolId: $f['school']->id, termId: $f['term']->id, hostelId: $hostel->id,
        damageType: 'window', description: 'Broken window pane', liability: 'shared_room',
        currency: 'USD', reportedByUserId: $f['user']->id, reportedAt: now(),
        liableStudentIds: $students->pluck('id')->all(),
    ));

    $approved = app(ApproveHostelDamageChargeAction::class)->execute(new ApproveHostelDamageChargeData(
        damageId: $damage->id, feeComponentId: $component->id, actualCostMinor: 10001, approvedByUserId: $f['user']->id,
    ));

    $chargeIds = $approved->ad_hoc_charge_ids;
    expect($chargeIds)->toHaveCount(6);

    $sum = AdHocCharge::whereIn('id', $chargeIds)->sum('amount_minor');
    expect((int) $sum)->toBe(10001)
        ->and($approved->charge_status)->toBe('charged');

    $disputed = app(DisputeHostelDamageChargeAction::class)->execute(new DisputeHostelDamageChargeData(
        damageId: $damage->id, disputeReason: 'Learner says it was already broken.', disputedByUserId: $f['user']->id,
    ));

    expect($disputed->charge_status)->toBe('disputed');
});
