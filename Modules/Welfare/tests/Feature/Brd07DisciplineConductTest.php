<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Boarding\Domain\Actions\CreateRollCallPointAction;
use Modules\Boarding\Domain\Actions\OpenRollCallAction;
use Modules\Boarding\Domain\DataObjects\CreateRollCallPointData;
use Modules\Boarding\Domain\DataObjects\OpenRollCallData;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\RollCallRecord;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\ApproveSanctionAction;
use Modules\Welfare\Domain\Actions\CreateBehaviourCategoryAction;
use Modules\Welfare\Domain\Actions\CreateSanctionTypeAction;
use Modules\Welfare\Domain\Actions\DecideAppealAction;
use Modules\Welfare\Domain\Actions\IssueSanctionAction;
use Modules\Welfare\Domain\Actions\LodgeAppealAction;
use Modules\Welfare\Domain\Actions\RebuildBehaviourPointBalanceAction;
use Modules\Welfare\Domain\Actions\RecordBehaviourAction;
use Modules\Welfare\Domain\Actions\RecordDisciplinaryCommitteeAction;
use Modules\Welfare\Domain\Actions\ScheduleDetentionAction;
use Modules\Welfare\Domain\DataObjects\CreateBehaviourCategoryData;
use Modules\Welfare\Domain\DataObjects\CreateSanctionTypeData;
use Modules\Welfare\Domain\DataObjects\IssueSanctionData;
use Modules\Welfare\Domain\DataObjects\RecordBehaviourData;
use Modules\Welfare\Domain\DataObjects\RecordDisciplinaryCommitteeData;
use Modules\Welfare\Domain\DataObjects\ScheduleDetentionData;

/**
 * @return array{school: School, year: AcademicYear, term: Term, gradeLevel: GradeLevel, user: User}
 */
function brd07Fixture(): array
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
function brd07Student(array $f): Student
{
    return Student::factory()->create(['school_id' => $f['school']->id, 'grade_level_id' => $f['gradeLevel']->id]);
}

it('records both polarities identically and rebuilds the conduct grade from records', function (): void {
    $f = brd07Fixture();
    $student = brd07Student($f);

    $demerit = app(CreateBehaviourCategoryAction::class)->execute(new CreateBehaviourCategoryData(
        schoolId: $f['school']->id, code: 'LATE', name: 'Late to class', polarity: 'negative', defaultPoints: -5,
    ));
    $merit = app(CreateBehaviourCategoryAction::class)->execute(new CreateBehaviourCategoryData(
        schoolId: $f['school']->id, code: 'HELP', name: 'Helped a peer', polarity: 'positive', defaultPoints: 5,
    ));

    $negativeRecord = app(RecordBehaviourAction::class)->execute(new RecordBehaviourData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        categoryId: $demerit->id, description: 'Arrived 10 minutes late.', occurredAt: now(), reportedByUserId: $f['user']->id,
    ));
    $positiveRecord = app(RecordBehaviourAction::class)->execute(new RecordBehaviourData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        categoryId: $merit->id, description: 'Helped a struggling classmate.', occurredAt: now(), reportedByUserId: $f['user']->id,
    ));

    expect($negativeRecord->status)->toBe('recorded')
        ->and($positiveRecord->status)->toBe('recorded')
        ->and($negativeRecord->points)->toBe(-5)
        ->and($positiveRecord->points)->toBe(5);

    $balance = app(RebuildBehaviourPointBalanceAction::class)->execute($f['school']->id, $student->id, $f['term']->id);

    expect($balance->merit_points)->toBe(5)
        ->and($balance->demerit_points)->toBe(5)
        ->and($balance->net_points)->toBe(0)
        ->and($balance->record_count)->toBe(2)
        ->and($balance->conduct_grade)->toBe('Good');
});

it('pauses the disciplinary process for a safeguarding-trigger category and refuses a sanction on the paused record', function (): void {
    $f = brd07Fixture();
    $student = brd07Student($f);

    $trigger = app(CreateBehaviourCategoryAction::class)->execute(new CreateBehaviourCategoryData(
        schoolId: $f['school']->id, code: 'ABSCOND', name: 'Absconding', polarity: 'negative', defaultPoints: 0,
        severityLevel: 5, isSafeguardingTrigger: true,
    ));

    $record = app(RecordBehaviourAction::class)->execute(new RecordBehaviourData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        categoryId: $trigger->id, description: 'Learner left campus without permission, third time this term.',
        occurredAt: now(), reportedByUserId: $f['user']->id,
    ));

    expect($record->status)->toBe('under_review')
        ->and($record->is_confidential)->toBeTrue()
        ->and($record->safeguarding_case_id)->toBeNull();

    $sanctionType = app(CreateSanctionTypeAction::class)->execute(new CreateSanctionTypeData(
        schoolId: $f['school']->id, code: 'WRITTEN', name: 'Written warning', severityLevel: 1,
    ));

    expect(fn () => app(IssueSanctionAction::class)->execute(new IssueSanctionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        sanctionTypeId: $sanctionType->id, behaviourRecordIds: [$record->id], reason: 'Absconding.',
        startsOn: now(), issuedByUserId: $f['user']->id,
    )))->toThrow(ValidationException::class);
});

it('requires a committee record and boarding arrangements before a campus-removing sanction takes effect', function (): void {
    $f = brd07Fixture();
    $student = Student::factory()->boarder()->create(['school_id' => $f['school']->id, 'grade_level_id' => $f['gradeLevel']->id]);

    $demerit = app(CreateBehaviourCategoryAction::class)->execute(new CreateBehaviourCategoryData(
        schoolId: $f['school']->id, code: 'FIGHT', name: 'Fighting', polarity: 'negative', defaultPoints: -10, severityLevel: 4,
    ));
    $record = app(RecordBehaviourAction::class)->execute(new RecordBehaviourData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        categoryId: $demerit->id, description: 'Involved in a physical fight.', occurredAt: now(), reportedByUserId: $f['user']->id,
    ));

    $suspension = app(CreateSanctionTypeAction::class)->execute(new CreateSanctionTypeData(
        schoolId: $f['school']->id, code: 'SUSPENSION', name: 'Suspension', severityLevel: 5,
        requiresCommittee: true, removesFromCampus: true, maxDurationDays: 5,
    ));

    expect(fn () => app(IssueSanctionAction::class)->execute(new IssueSanctionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        sanctionTypeId: $suspension->id, behaviourRecordIds: [$record->id], reason: 'Fighting.',
        startsOn: now(), issuedByUserId: $f['user']->id,
    )))->toThrow(ValidationException::class);

    $committee = app(RecordDisciplinaryCommitteeAction::class)->execute(new RecordDisciplinaryCommitteeData(
        schoolId: $f['school']->id, studentId: $student->id, convenedOn: now(), panelStaffIds: [1, 2, 3],
        learnerStatement: 'The learner explained the altercation.', findings: 'Both learners were involved.',
        decision: 'sanction', chairedByUserId: $f['user']->id,
    ));

    expect(fn () => app(IssueSanctionAction::class)->execute(new IssueSanctionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        sanctionTypeId: $suspension->id, behaviourRecordIds: [$record->id], reason: 'Fighting.',
        startsOn: now(), issuedByUserId: $f['user']->id, committeeRecordId: $committee->id,
    )))->toThrow(ValidationException::class);

    $sanction = app(IssueSanctionAction::class)->execute(new IssueSanctionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        sanctionTypeId: $suspension->id, behaviourRecordIds: [$record->id], reason: 'Fighting.',
        startsOn: now(), issuedByUserId: $f['user']->id, committeeRecordId: $committee->id,
        boardingArrangements: 'Guardian to collect from the hostel by 17:00.',
    ));

    expect($sanction->status)->toBe('pending_approval');
});

it('overturns a sanction on appeal without deleting it', function (): void {
    $f = brd07Fixture();
    $student = brd07Student($f);

    $sanctionType = app(CreateSanctionTypeAction::class)->execute(new CreateSanctionTypeData(
        schoolId: $f['school']->id, code: 'DETENTION', name: 'Detention', severityLevel: 1,
    ));

    $sanction = app(IssueSanctionAction::class)->execute(new IssueSanctionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        sanctionTypeId: $sanctionType->id, behaviourRecordIds: [], reason: 'Persistent lateness.',
        startsOn: now(), issuedByUserId: $f['user']->id,
    ));

    expect($sanction->status)->toBe('active');

    $appeal = app(LodgeAppealAction::class)->execute($sanction->id, null, true, 'The learner disputes the account.');
    expect($sanction->fresh()->status)->toBe('appealed');

    app(DecideAppealAction::class)->execute($appeal->id, 'overturned', 'New evidence changed the finding.', $f['user']->id);

    $sanctionAfter = $sanction->fresh();
    expect($sanctionAfter)->not->toBeNull()
        ->and($sanctionAfter->status)->toBe('overturned')
        ->and($sanctionAfter->exists)->toBeTrue();
});

it('closes the detention and suspended roll-status stubs', function (): void {
    $f = brd07Fixture();
    $hostel = Hostel::factory()->create(['school_id' => $f['school']->id, 'gender' => 'male']);
    $room = HostelRoom::factory()->create(['school_id' => $f['school']->id, 'hostel_id' => $hostel->id]);

    $detainedStudent = Student::factory()->boarder()->create(['school_id' => $f['school']->id, 'gender' => 'male', 'grade_level_id' => $f['gradeLevel']->id]);
    $suspendedStudent = Student::factory()->boarder()->create(['school_id' => $f['school']->id, 'gender' => 'male', 'grade_level_id' => $f['gradeLevel']->id]);

    foreach ([$detainedStudent, $suspendedStudent] as $student) {
        $bed = HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $room->id]);
        BedAllocation::factory()->create([
            'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
            'student_id' => $student->id, 'bed_id' => $bed->id, 'hostel_id' => $hostel->id, 'room_id' => $room->id,
            'status' => 'confirmed', 'allocated_by' => $f['user']->id,
        ]);
    }

    app(ScheduleDetentionAction::class)->execute(new ScheduleDetentionData(
        schoolId: $f['school']->id, termId: $f['term']->id, studentId: $detainedStudent->id,
        scheduledDate: now(), startsAt: '17:30:00', endsAt: '18:30:00',
    ));

    $sanctionType = app(CreateSanctionTypeAction::class)->execute(new CreateSanctionTypeData(
        schoolId: $f['school']->id, code: 'SUSPEND2', name: 'Suspension', severityLevel: 5, removesFromCampus: true,
    ));
    $sanction = app(IssueSanctionAction::class)->execute(new IssueSanctionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $suspendedStudent->id,
        sanctionTypeId: $sanctionType->id, behaviourRecordIds: [], reason: 'Serious misconduct.',
        startsOn: now(), issuedByUserId: $f['user']->id,
        boardingArrangements: 'Guardian to collect from the hostel by 17:00.',
    ));
    expect($sanction->status)->toBe('pending_approval');
    app(ApproveSanctionAction::class)->execute($sanction->id, $f['user']->id);

    $point = app(CreateRollCallPointAction::class)->execute(new CreateRollCallPointData(
        schoolId: $f['school']->id, code: 'SUPPER', name: 'Supper', scheduledTime: '18:00:00', appliesOnDays: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
    ));
    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $point->id, hostelId: $hostel->id, termId: $f['term']->id, rollDate: now(),
    ));

    $detainedRecord = RollCallRecord::where('roll_call_id', $rollCall->id)->where('student_id', $detainedStudent->id)->first();
    $suspendedRecord = RollCallRecord::where('roll_call_id', $rollCall->id)->where('student_id', $suspendedStudent->id)->first();

    expect($detainedRecord?->status)->toBe('detention')
        ->and($suspendedRecord?->status)->toBe('suspended');
});
