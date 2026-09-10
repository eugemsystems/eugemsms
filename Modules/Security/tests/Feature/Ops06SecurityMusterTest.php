<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSession;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Security\Domain\Actions\ApproveContractorAction;
use Modules\Security\Domain\Actions\AssembleMusterRollAction;
use Modules\Security\Domain\Actions\CheckGateAccessAction;
use Modules\Security\Domain\Actions\CheckMissedPatrolsAction;
use Modules\Security\Domain\Actions\CheckOverdueKeysAction;
use Modules\Security\Domain\Actions\ClaimLostPropertyAction;
use Modules\Security\Domain\Actions\CompleteMusterAction;
use Modules\Security\Domain\Actions\CompletePatrolAction;
use Modules\Security\Domain\Actions\CreateContractorAction;
use Modules\Security\Domain\Actions\CreateContractorWorkerAction;
use Modules\Security\Domain\Actions\CreatePatrolRouteAction;
use Modules\Security\Domain\Actions\IssueKeyAction;
use Modules\Security\Domain\Actions\RecordMusterMarkAction;
use Modules\Security\Domain\Actions\RecordOccurrenceAction;
use Modules\Security\Domain\Actions\RecordPatrolScanAction;
use Modules\Security\Domain\Actions\ReportLostPropertyAction;
use Modules\Security\Domain\Actions\SchedulePatrolAction;
use Modules\Security\Domain\Actions\SignInContractorWorkerAction;
use Modules\Security\Domain\Actions\TriggerEmergencyDrillAction;
use Modules\Security\Domain\DataObjects\ApproveContractorData;
use Modules\Security\Domain\DataObjects\CreateContractorData;
use Modules\Security\Domain\DataObjects\CreateContractorWorkerData;
use Modules\Security\Domain\DataObjects\CreatePatrolRouteData;
use Modules\Security\Domain\DataObjects\IssueKeyData;
use Modules\Security\Domain\DataObjects\RecordOccurrenceData;
use Modules\Security\Domain\DataObjects\ReportLostPropertyData;
use Modules\Security\Domain\DataObjects\TriggerEmergencyDrillData;
use Modules\Security\Domain\Events\KeyOverdue;
use Modules\Security\Domain\Events\OccurrenceRequiresImmediateNotice;
use Modules\Security\Domain\Events\PatrolIncomplete;
use Modules\Security\Domain\Events\PatrolMissed;
use Modules\Security\Domain\Exceptions\GateAccessRefusedException;
use Modules\Security\Domain\Exceptions\MasterKeyRequiresAuthorityException;
use Modules\Security\Models\KeyAndCard;
use Modules\Welfare\Models\MedicalCondition;
use Modules\Welfare\Models\SickBayAdmission;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, user2: User}
 */
function ops06Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true, 'financial_state' => 'open']);
    $user = User::factory()->create();
    $user2 = User::factory()->create();

    foreach (['occurrence_book'] as $type) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $type, pattern: strtoupper(substr($type, 0, 3)).'/{SEQ:6}',
        ));
    }

    return compact('school', 'year', 'term', 'user', 'user2');
}

/**
 * `AttendanceRecordFactory`/`AttendanceSessionFactory` build their own
 * phantom school/year/term chain when a caller doesn't override every
 * nested key — every FK is set explicitly here to a real, already-
 * created fixture row so no phantom row is ever created.
 *
 * @param  array{school: School, year: AcademicYear, term: Term, user: User, user2: User}  $f
 */
function ops06MarkPresentToday(array $f, Student $student, string $status = 'present'): AttendanceRecord
{
    $class = SchoolClass::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'grade_level_id' => GradeLevel::factory(),
    ]);
    $session = AttendanceSession::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'class_id' => $class->id, 'session_date' => now()->toDateString(),
    ]);

    return AttendanceRecord::factory()->create([
        'school_id' => $f['school']->id, 'session_id' => $session->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'session_date' => now()->toDateString(), 'status' => $status,
    ]);
}

/**
 * `BedAllocationFactory` builds its own phantom school/year/term/
 * hostel/room/bed chain when a caller doesn't override every nested
 * key — every FK is set explicitly here to a real fixture row.
 *
 * @param  array{school: School, year: AcademicYear, term: Term, user: User, user2: User}  $f
 */
function ops06AllocateBoarder(array $f, Student $student, string $hostelCode): BedAllocation
{
    $hostel = Hostel::factory()->create(['school_id' => $f['school']->id, 'code' => $hostelCode]);
    $room = HostelRoom::factory()->create(['school_id' => $f['school']->id, 'hostel_id' => $hostel->id]);
    $bed = HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $room->id]);

    return BedAllocation::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'hostel_id' => $hostel->id, 'room_id' => $room->id, 'bed_id' => $bed->id,
        'status' => 'confirmed', 'allocated_by' => $f['user']->id,
    ]);
}

it('refuses to approve a contractor with expired insurance (BR-OPS-06-001)', function (): void {
    $f = ops06Fixture();
    $contractor = app(CreateContractorAction::class)->execute(new CreateContractorData(
        schoolId: $f['school']->id, companyName: 'Acme Builders',
    ));

    expect(fn () => app(ApproveContractorAction::class)->execute($contractor->id, new ApproveContractorData(
        insuranceExpiresOn: Carbon::yesterday(), safetyInductionOn: Carbon::now(), inductionValidUntil: Carbon::now()->addYear(),
        approvedByUserId: $f['user2']->id,
    )))->toThrow(ValidationException::class);
});

it('refuses gate access to a contractor worker with no police clearance, and grants it once cleared (BR-OPS-06-002/AC-OPS-06-004)', function (): void {
    $f = ops06Fixture();
    $contractor = app(CreateContractorAction::class)->execute(new CreateContractorData(
        schoolId: $f['school']->id, companyName: 'Acme Builders',
    ));
    app(ApproveContractorAction::class)->execute($contractor->id, new ApproveContractorData(
        insuranceExpiresOn: Carbon::now()->addMonths(6), safetyInductionOn: Carbon::now(), inductionValidUntil: Carbon::now()->addYear(),
        approvedByUserId: $f['user2']->id,
    ));

    $uncleared = app(CreateContractorWorkerAction::class)->execute(new CreateContractorWorkerData(
        schoolId: $f['school']->id, contractorId: $contractor->id, fullName: 'John Worker',
    ));

    expect(app(CheckGateAccessAction::class)->execute($uncleared->id))->not->toBeNull();

    expect(fn () => app(SignInContractorWorkerAction::class)->execute($uncleared->id, $f['user']->id))
        ->toThrow(GateAccessRefusedException::class);

    $cleared = app(CreateContractorWorkerAction::class)->execute(new CreateContractorWorkerData(
        schoolId: $f['school']->id, contractorId: $contractor->id, fullName: 'Jane Worker',
        inductionCompletedOn: Carbon::now(), policeClearanceOn: Carbon::now(),
    ));

    expect(app(CheckGateAccessAction::class)->execute($cleared->id))->toBeNull();

    $visit = app(SignInContractorWorkerAction::class)->execute($cleared->id, $f['user']->id);
    expect($visit->signed_out_at)->toBeNull();
});

it('records a gapless, append-only occurrence book, refuses edits, and corrects via a new entry (BR-OPS-06-003/AC-OPS-06-005)', function (): void {
    $f = ops06Fixture();

    $first = app(RecordOccurrenceAction::class)->execute(new RecordOccurrenceData(
        schoolId: $f['school']->id, occurredAt: Carbon::now(), category: 'observation',
        description: 'Routine check.', recordedByUserId: $f['user']->id,
    ));
    $second = app(RecordOccurrenceAction::class)->execute(new RecordOccurrenceData(
        schoolId: $f['school']->id, occurredAt: Carbon::now(), category: 'observation',
        description: 'Another routine check.', recordedByUserId: $f['user']->id,
    ));

    expect($second->entry_number)->toBe($first->entry_number + 1);

    expect(fn () => $first->update(['description' => 'Edited.']))->toThrow(InvalidStateTransitionException::class);
    expect(fn () => $first->delete())->toThrow(InvalidStateTransitionException::class);

    $correction = app(RecordOccurrenceAction::class)->execute(new RecordOccurrenceData(
        schoolId: $f['school']->id, occurredAt: Carbon::now(), category: 'observation',
        description: 'Correction: the first entry misstated the time.', recordedByUserId: $f['user']->id,
        correctsEntryId: $first->id,
    ));

    expect($correction->corrects_entry_id)->toBe($first->id);
});

it('notifies the head immediately on an intrusion, fire or medical occurrence (BR-OPS-06-012)', function (): void {
    Event::fake([OccurrenceRequiresImmediateNotice::class]);
    $f = ops06Fixture();

    app(RecordOccurrenceAction::class)->execute(new RecordOccurrenceData(
        schoolId: $f['school']->id, occurredAt: Carbon::now(), category: 'intrusion',
        description: 'Unidentified person seen climbing the boundary fence.', recordedByUserId: $f['user']->id,
    ));

    Event::assertDispatched(OccurrenceRequiresImmediateNotice::class);
});

it('marks a patrol incomplete when fewer checkpoints are scanned than expected, and detects a missed patrol (BR-OPS-06-004/005)', function (): void {
    Event::fake([PatrolIncomplete::class, PatrolMissed::class]);
    $f = ops06Fixture();
    $checkpoint1 = MovementCheckpoint::factory()->create(['school_id' => $f['school']->id, 'code' => 'GATE_1']);
    $checkpoint2 = MovementCheckpoint::factory()->create(['school_id' => $f['school']->id, 'code' => 'GATE_2']);
    $route = app(CreatePatrolRouteAction::class)->execute(new CreatePatrolRouteData(
        schoolId: $f['school']->id, code: 'RT-1', name: 'Perimeter', checkpointIds: [$checkpoint1->id, $checkpoint2->id],
        frequency: 'two_hourly',
    ));
    $guard = Staff::factory()->for($f['school'])->create();

    $patrol = app(SchedulePatrolAction::class)->execute($route->id, $guard->id, Carbon::now());
    expect($patrol->checkpoints_expected)->toBe(2);

    app(RecordPatrolScanAction::class)->execute($patrol->id, $checkpoint1->id, 'qr');
    $completed = app(CompletePatrolAction::class)->execute($patrol->id, 'Second checkpoint inaccessible.');

    expect($completed->status)->toBe('incomplete');
    Event::assertDispatched(PatrolIncomplete::class);

    $missedPatrol = app(SchedulePatrolAction::class)->execute($route->id, $guard->id, Carbon::now()->subMinutes(30));
    $missed = app(CheckMissedPatrolsAction::class)->execute($f['school']->id);

    expect($missed->pluck('id'))->toContain($missedPatrol->id);
    Event::assertDispatched(PatrolMissed::class);
});

it('requires higher authority to issue a master key, and alerts when a key is overdue (BR-OPS-06-006/007)', function (): void {
    Event::fake([KeyOverdue::class]);
    $f = ops06Fixture();
    $master = KeyAndCard::factory()->master()->create(['school_id' => $f['school']->id]);
    $staff = Staff::factory()->for($f['school'])->create();

    expect(fn () => app(IssueKeyAction::class)->execute(new IssueKeyData(
        keyId: $master->id, issuedByUserId: $f['user']->id, issuedToStaffId: $staff->id,
    )))->toThrow(MasterKeyRequiresAuthorityException::class);

    $issue = app(IssueKeyAction::class)->execute(new IssueKeyData(
        keyId: $master->id, issuedByUserId: $f['user']->id, issuedToStaffId: $staff->id,
        dueBackOn: Carbon::yesterday(), higherAuthorityConfirmed: true,
    ));

    expect($master->fresh()->status)->toBe('issued');

    $overdue = app(CheckOverdueKeysAction::class)->execute($f['school']->id);

    expect($overdue->pluck('id'))->toContain($issue->id);
    Event::assertDispatched(KeyOverdue::class);
});

it('allows a student to claim held lost property (BR-OPS-06 §2)', function (): void {
    $f = ops06Fixture();
    $student = Student::factory()->for($f['school'])->create();

    $item = app(ReportLostPropertyAction::class)->execute(new ReportLostPropertyData(
        schoolId: $f['school']->id, foundOn: Carbon::now(), description: 'Blue water bottle',
    ));

    $claimed = app(ClaimLostPropertyAction::class)->execute($item->id, $student->id);

    expect($claimed->status)->toBe('claimed')
        ->and($claimed->claimed_by_student_id)->toBe($student->id);
});

it('assembles the muster roll from boarders minus exeats, day scholars present, staff minus leave, visitors and contractors, with sick bay listed separately and first (BR-OPS-06-008/009/AC-OPS-06-001)', function (): void {
    $f = ops06Fixture();

    // A boarder currently on the property.
    $presentBoarder = Student::factory()->boarder()->for($f['school'])->create();
    ops06AllocateBoarder($f, $presentBoarder, 'HSE-1');

    // A boarder currently out on an approved exeat — excluded.
    $outBoarder = Student::factory()->boarder()->for($f['school'])->create();
    ops06AllocateBoarder($f, $outBoarder, 'HSE-2');
    Exeat::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $outBoarder->id, 'status' => 'departed',
    ]);

    // A day scholar marked present today.
    $dayScholar = Student::factory()->for($f['school'])->create(['residency' => 'DAY']);
    ops06MarkPresentToday($f, $dayScholar);

    // A day scholar marked absent today — excluded.
    $absentDayScholar = Student::factory()->for($f['school'])->create(['residency' => 'DAY']);
    ops06MarkPresentToday($f, $absentDayScholar, 'absent');

    // Active staff.
    $activeStaff = Staff::factory()->for($f['school'])->create(['status' => 'active']);

    // Staff on approved leave covering today — excluded.
    $staffOnLeave = Staff::factory()->for($f['school'])->create(['status' => 'active']);
    LeaveRequest::factory()->create([
        'school_id' => $f['school']->id, 'staff_id' => $staffOnLeave->id, 'status' => 'approved',
        'starts_on' => now()->subDay()->toDateString(), 'ends_on' => now()->addDay()->toDateString(),
    ]);

    // A visitor signed in, not signed out.
    $visitorLog = VisitorLogEntry::factory()->create(['school_id' => $f['school']->id]);

    // A contractor worker signed in, not signed out.
    $contractor = app(CreateContractorAction::class)->execute(new CreateContractorData(schoolId: $f['school']->id, companyName: 'Acme'));
    app(ApproveContractorAction::class)->execute($contractor->id, new ApproveContractorData(
        insuranceExpiresOn: Carbon::now()->addMonths(6), safetyInductionOn: Carbon::now(), inductionValidUntil: Carbon::now()->addYear(),
        approvedByUserId: $f['user2']->id,
    ));
    $worker = app(CreateContractorWorkerAction::class)->execute(new CreateContractorWorkerData(
        schoolId: $f['school']->id, contractorId: $contractor->id, fullName: 'Site Worker',
        inductionCompletedOn: Carbon::now(), policeClearanceOn: Carbon::now(),
    ));
    $visit = app(SignInContractorWorkerAction::class)->execute($worker->id, $f['user']->id);

    // A boarder currently in sick bay — must appear ONLY under sick_bay, not also as a boarder.
    $sickBoarder = Student::factory()->boarder()->for($f['school'])->create();
    ops06AllocateBoarder($f, $sickBoarder, 'HSE-3');
    SickBayAdmission::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $sickBoarder->id, 'status' => 'admitted',
    ]);

    // A day scholar with a recorded mobility/evacuation-assistance requirement.
    $assistanceScholar = Student::factory()->for($f['school'])->create(['residency' => 'DAY']);
    ops06MarkPresentToday($f, $assistanceScholar);
    MedicalCondition::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $assistanceScholar->id, 'requires_evacuation_assistance' => true,
    ]);

    $roster = app(AssembleMusterRollAction::class)->execute($f['school']->id);

    $byCategory = $roster->groupBy('category')->map(fn ($entries) => $entries->pluck('personId'));

    expect($byCategory->get('boarder', collect()))->toContain($presentBoarder->id)
        ->and($byCategory->get('boarder', collect()))->not->toContain($outBoarder->id)
        ->and($byCategory->get('boarder', collect()))->not->toContain($sickBoarder->id)
        ->and($byCategory->get('day_scholar', collect()))->toContain($dayScholar->id)
        ->and($byCategory->get('day_scholar', collect()))->not->toContain($absentDayScholar->id)
        ->and($byCategory->get('staff', collect()))->toContain($activeStaff->id)
        ->and($byCategory->get('staff', collect()))->not->toContain($staffOnLeave->id)
        ->and($byCategory->get('visitor', collect()))->toContain($visitorLog->id)
        ->and($byCategory->get('contractor', collect()))->toContain($visit->id)
        ->and($byCategory->get('sick_bay', collect()))->toContain($sickBoarder->id);

    // Sick bay and assistance-flagged entries sort first.
    $firstEntries = $roster->take($byCategory->get('sick_bay', collect())->count() + 1);
    expect($firstEntries->first()->needsAssistance)->toBeTrue();

    $assistanceEntry = $roster->first(fn ($entry) => $entry->personId === $assistanceScholar->id && $entry->category === 'day_scholar');
    expect($assistanceEntry->needsAssistance)->toBeTrue();
});

it('opens a real BRD-02 missing-learner incident for an unaccounted learner at the end of a muster (BR-OPS-06-011/AC-OPS-06-003)', function (): void {
    $f = ops06Fixture();

    $accountedFor = Student::factory()->for($f['school'])->create(['residency' => 'DAY']);
    ops06MarkPresentToday($f, $accountedFor);

    $unaccounted = Student::factory()->for($f['school'])->create(['residency' => 'DAY']);
    ops06MarkPresentToday($f, $unaccounted);

    $drill = app(TriggerEmergencyDrillAction::class)->execute(new TriggerEmergencyDrillData(
        schoolId: $f['school']->id, termId: $f['term']->id, drillType: 'fire', conductedByUserId: $f['user']->id,
    ));

    expect($drill->expected_headcount)->toBe(2);

    app(RecordMusterMarkAction::class)->execute($f['school']->id, $drill->id, 'student', $accountedFor->id, $f['user']->id, 'Assembly Point A');

    $completed = app(CompleteMusterAction::class)->execute($drill->id, $f['term']->id, evacuationSeconds: 240);

    expect($completed->mustered_headcount)->toBe(1)
        ->and($completed->unaccounted_count)->toBe(1)
        ->and($completed->evacuation_seconds)->toBe(240);

    $incident = MissingLearnerIncident::where('school_id', $f['school']->id)->where('student_id', $unaccounted->id)->first();
    expect($incident)->not->toBeNull()
        ->and($incident->roll_call_id)->toBeNull();

    $noIncidentForAccounted = MissingLearnerIncident::where('school_id', $f['school']->id)->where('student_id', $accountedFor->id)->first();
    expect($noIncidentForAccounted)->toBeNull();
});
