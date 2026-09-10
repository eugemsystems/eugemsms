<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\Actions\ApproveExeatAction;
use Modules\Boarding\Domain\Actions\CheckOverdueExeatAction;
use Modules\Boarding\Domain\Actions\CreateExeatTypeAction;
use Modules\Boarding\Domain\Actions\CreateRollCallPointAction;
use Modules\Boarding\Domain\Actions\OpenRollCallAction;
use Modules\Boarding\Domain\Actions\RecordDepartureAction;
use Modules\Boarding\Domain\Actions\RecordReturnAction;
use Modules\Boarding\Domain\Actions\RequestExeatAction;
use Modules\Boarding\Domain\Actions\SignInVisitorAction;
use Modules\Boarding\Domain\DataObjects\ApproveExeatData;
use Modules\Boarding\Domain\DataObjects\CollectionClaim;
use Modules\Boarding\Domain\DataObjects\CreateExeatTypeData;
use Modules\Boarding\Domain\DataObjects\CreateRollCallPointData;
use Modules\Boarding\Domain\DataObjects\OpenRollCallData;
use Modules\Boarding\Domain\DataObjects\RecordDepartureData;
use Modules\Boarding\Domain\DataObjects\RecordReturnData;
use Modules\Boarding\Domain\DataObjects\RequestExeatData;
use Modules\Boarding\Domain\DataObjects\SignInVisitorData;
use Modules\Boarding\Domain\Exceptions\VisitorBlacklistedException;
use Modules\Boarding\Domain\Support\CollectionAuthorityChecker;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\CollectionAttempt;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Boarding\Models\RollCallRecord;
use Modules\Boarding\Models\Visitor;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\Actions\LinkGuardianToStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User}
 */
function brd03Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'exeat', pattern: '{TYPE}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    return compact('school', 'year', 'term', 'section', 'gradeLevel', 'user');
}

/**
 * @param  array<string, mixed>  $f
 */
function brd03Student(array $f, string $firstName): Student
{
    return app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        firstName: $firstName, lastName: 'Moyo', dateOfBirth: now()->subYears(14), gender: 'male',
        enrolmentType: 'FULL_TIME', residency: 'BOARDER', sectionId: $f['section']->id,
        gradeLevelId: $f['gradeLevel']->id, entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id, skipDuplicateCheck: true,
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function brd03LinkGuardian(array $f, Student $student, Guardian $guardian, bool $hasCourtRestriction = false): void
{
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $student->id, guardianId: $guardian->id, relationship: 'mother',
        createdByUserId: $f['user']->id, mayCollectLearner: true,
        hasCourtRestriction: $hasCourtRestriction, effectiveFrom: now(),
    ));
}

it('refuses release with no active exeat', function (): void {
    $f = brd03Fixture();
    $student = brd03Student($f, 'Tino');

    // Create an exeat type + a rejected/nonexistent exeat scenario: no exeat at all.
    $checker = app(CollectionAuthorityChecker::class);
    $decision = $checker->check($student, new CollectionClaim(name: 'Random Person'), null);

    expect($decision->released)->toBeFalse()
        ->and($decision->refusalReason)->toBe('no_exeat')
        ->and($decision->escalate)->toBeTrue();
});

it('refuses release to an adult not named on the exeat and records the attempt permanently', function (): void {
    $f = brd03Fixture();
    $student = brd03Student($f, 'Rudo');
    $guardian = Guardian::factory()->for($f['school'])->create();
    brd03LinkGuardian($f, $student, $guardian, hasCourtRestriction: false);

    $exeatType = app(CreateExeatTypeAction::class)->execute(new CreateExeatTypeData(
        schoolId: $f['school']->id, code: 'WEEKEND', name: 'Weekend Exeat',
    ));

    $exeat = app(RequestExeatAction::class)->execute(new RequestExeatData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        studentId: $student->id, exeatTypeId: $exeatType->id, reason: 'Family visit.',
        departsAt: now()->addHours(2), returnsBy: now()->addDays(2),
        destinationAddress: '10 Samora Machel Ave', destinationProvince: 'Harare',
        contactPhone: '+263771111111', collectionMethod: 'guardian_collect',
        requestSource: 'guardian_portal', requestedByGuardianId: $guardian->id,
        collectingGuardianId: $guardian->id,
    ));

    app(ApproveExeatAction::class)->execute(new ApproveExeatData(exeatId: $exeat->id, approvedByUserId: $f['user']->id));

    $attempt = app(RecordDepartureAction::class)->execute(new RecordDepartureData(
        exeatId: $exeat->id,
        claim: new CollectionClaim(name: 'Stranger Danger', guardianId: null),
        gateStaffUserId: $f['user']->id,
    ));

    expect($attempt->outcome)->toBe('escalated')
        ->and($attempt->refusal_reason)->toBe('no_right');

    expect(fn () => $attempt->delete())->toThrow(InvalidStateTransitionException::class)
        ->and(CollectionAttempt::find($attempt->id))->not->toBeNull();
});

it('refuses release when the collecting guardian has a court restriction, overriding every other right', function (): void {
    $f = brd03Fixture();
    $student = brd03Student($f, 'Chido');
    $guardian = Guardian::factory()->for($f['school'])->create();
    brd03LinkGuardian($f, $student, $guardian, hasCourtRestriction: true);

    $exeatType = app(CreateExeatTypeAction::class)->execute(new CreateExeatTypeData(
        schoolId: $f['school']->id, code: 'WEEKEND', name: 'Weekend Exeat',
    ));

    $exeat = app(RequestExeatAction::class)->execute(new RequestExeatData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        studentId: $student->id, exeatTypeId: $exeatType->id, reason: 'Family visit.',
        departsAt: now()->addHours(2), returnsBy: now()->addDays(2),
        destinationAddress: '10 Samora Machel Ave', destinationProvince: 'Harare',
        contactPhone: '+263771111111', collectionMethod: 'guardian_collect',
        requestSource: 'guardian_portal', requestedByGuardianId: $guardian->id,
        collectingGuardianId: $guardian->id,
    ));
    app(ApproveExeatAction::class)->execute(new ApproveExeatData(exeatId: $exeat->id, approvedByUserId: $f['user']->id));

    $attempt = app(RecordDepartureAction::class)->execute(new RecordDepartureData(
        exeatId: $exeat->id,
        claim: new CollectionClaim(name: 'Father', guardianId: $guardian->id, identityVerified: true),
        gateStaffUserId: $f['user']->id,
    ));

    expect($attempt->outcome)->toBe('escalated')
        ->and($attempt->refusal_reason)->toBe('court_restriction');
});

it('releases to the named collecting guardian once identity is verified', function (): void {
    $f = brd03Fixture();
    $student = brd03Student($f, 'Tanaka');
    $guardian = Guardian::factory()->for($f['school'])->create();
    brd03LinkGuardian($f, $student, $guardian, hasCourtRestriction: false);

    $exeatType = app(CreateExeatTypeAction::class)->execute(new CreateExeatTypeData(
        schoolId: $f['school']->id, code: 'WEEKEND', name: 'Weekend Exeat',
    ));

    $exeat = app(RequestExeatAction::class)->execute(new RequestExeatData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        studentId: $student->id, exeatTypeId: $exeatType->id, reason: 'Family visit.',
        departsAt: now()->addHours(2), returnsBy: now()->addDays(2),
        destinationAddress: '10 Samora Machel Ave', destinationProvince: 'Harare',
        contactPhone: '+263771111111', collectionMethod: 'guardian_collect',
        requestSource: 'guardian_portal', requestedByGuardianId: $guardian->id,
        collectingGuardianId: $guardian->id,
    ));
    app(ApproveExeatAction::class)->execute(new ApproveExeatData(exeatId: $exeat->id, approvedByUserId: $f['user']->id));

    $attempt = app(RecordDepartureAction::class)->execute(new RecordDepartureData(
        exeatId: $exeat->id,
        claim: new CollectionClaim(name: 'Mother', guardianId: $guardian->id, identityVerified: true),
        gateStaffUserId: $f['user']->id,
    ));

    expect($attempt->outcome)->toBe('released');

    $exeat->refresh();
    expect($exeat->status)->toBe('departed')
        ->and($exeat->departure_verified_by)->toBe('Mother');

    $returned = app(RecordReturnAction::class)->execute(new RecordReturnData(exeatId: $exeat->id, recordedByUserId: $f['user']->id));
    expect($returned->status)->toBe('returned')
        ->and($returned->late_return_minutes)->toBe(0);
});

it('opens a BRD-02 missing-learner incident once an exeat is overdue past the configured threshold', function (): void {
    $f = brd03Fixture();
    $student = brd03Student($f, 'Overdue');
    $guardian = Guardian::factory()->for($f['school'])->create();
    brd03LinkGuardian($f, $student, $guardian, hasCourtRestriction: false);

    $exeatType = app(CreateExeatTypeAction::class)->execute(new CreateExeatTypeData(
        schoolId: $f['school']->id, code: 'DAY_PASS', name: 'Day Pass',
    ));

    $exeat = app(RequestExeatAction::class)->execute(new RequestExeatData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        studentId: $student->id, exeatTypeId: $exeatType->id, reason: 'Day trip.',
        departsAt: now()->subMinutes(30), returnsBy: now()->addMinutes(10),
        destinationAddress: '10 Samora Machel Ave', destinationProvince: 'Harare',
        contactPhone: '+263771111111', collectionMethod: 'guardian_collect',
        requestSource: 'guardian_portal', requestedByGuardianId: $guardian->id,
        collectingGuardianId: $guardian->id,
    ));
    app(ApproveExeatAction::class)->execute(new ApproveExeatData(exeatId: $exeat->id, approvedByUserId: $f['user']->id));
    app(RecordDepartureAction::class)->execute(new RecordDepartureData(
        exeatId: $exeat->id,
        claim: new CollectionClaim(name: 'Mother', guardianId: $guardian->id, identityVerified: true),
        gateStaffUserId: $f['user']->id,
    ));

    // Advance past returns_by, and past the 180-minute default threshold.
    Carbon::setTestNow(now()->addMinutes(200));

    $checked = app(CheckOverdueExeatAction::class)->execute($exeat->id);

    Carbon::setTestNow();

    expect($checked->status)->toBe('overdue');

    $incident = MissingLearnerIncident::where('student_id', $student->id)->whereNull('roll_call_id')->first();
    expect($incident)->not->toBeNull()
        ->and($incident->status)->toBe('open');
});

it('pre-populates a roll call status as exeat for an approved, currently-active exeat', function (): void {
    // Pinned well before the roll call's own 21:00 scheduled time — this
    // test asserts an exeat covering that moment, which a real wall-clock
    // `now()` cannot guarantee once the test happens to run after 21:00.
    Carbon::setTestNow(Carbon::parse('09:00:00'));

    $f = brd03Fixture();
    $hostel = Hostel::factory()->create(['school_id' => $f['school']->id, 'gender' => 'male']);
    $room = HostelRoom::factory()->create(['school_id' => $f['school']->id, 'hostel_id' => $hostel->id]);
    $bed = HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $room->id]);
    $student = brd03Student($f, 'OnExeat');
    BedAllocation::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'bed_id' => $bed->id, 'hostel_id' => $hostel->id, 'room_id' => $room->id,
        'status' => 'confirmed', 'allocated_by' => $f['user']->id,
    ]);

    $guardian = Guardian::factory()->for($f['school'])->create();
    brd03LinkGuardian($f, $student, $guardian, hasCourtRestriction: false);
    $exeatType = app(CreateExeatTypeAction::class)->execute(new CreateExeatTypeData(
        schoolId: $f['school']->id, code: 'WEEKEND', name: 'Weekend Exeat',
    ));
    $exeat = app(RequestExeatAction::class)->execute(new RequestExeatData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        studentId: $student->id, exeatTypeId: $exeatType->id, reason: 'Weekend home.',
        departsAt: now()->subHour(), returnsBy: now()->addDay(),
        destinationAddress: '10 Samora Machel Ave', destinationProvince: 'Harare',
        contactPhone: '+263771111111', collectionMethod: 'guardian_collect',
        requestSource: 'guardian_portal', requestedByGuardianId: $guardian->id,
        collectingGuardianId: $guardian->id,
    ));
    app(ApproveExeatAction::class)->execute(new ApproveExeatData(exeatId: $exeat->id, approvedByUserId: $f['user']->id));

    $point = app(CreateRollCallPointAction::class)->execute(new CreateRollCallPointData(
        schoolId: $f['school']->id, code: 'LIGHTS_OUT', name: 'Lights Out', scheduledTime: '21:00:00', appliesOnDays: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
    ));

    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $point->id, hostelId: $hostel->id, termId: $f['term']->id, rollDate: now(),
    ));

    $record = RollCallRecord::where('roll_call_id', $rollCall->id)->where('student_id', $student->id)->first();

    Carbon::setTestNow();

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe('exeat')
        ->and($record->is_auto_populated)->toBeTrue();
});

it('refuses a blacklisted visitor at sign-in and never creates a visitor log for it', function (): void {
    $f = brd03Fixture();
    $visitor = Visitor::factory()->create(['school_id' => $f['school']->id, 'full_name' => 'Blocked Person', 'is_blacklisted' => true]);

    expect(fn () => app(SignInVisitorAction::class)->execute(new SignInVisitorData(
        schoolId: $f['school']->id, fullName: 'Blocked Person', visitPurpose: 'parent_visit', gateStaffUserId: $f['user']->id,
    )))->toThrow(VisitorBlacklistedException::class);

    expect(VisitorLogEntry::where('visitor_id', $visitor->id)->exists())->toBeFalse();
});
