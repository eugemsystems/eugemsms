<?php

use App\Models\User;
use Modules\Academic\Domain\Actions\AllocateClassAction;
use Modules\Academic\Domain\Actions\AmendAttendanceRecordAction;
use Modules\Academic\Domain\Actions\CreateAttendanceReasonCodeAction;
use Modules\Academic\Domain\Actions\GenerateAttendanceSessionAction;
use Modules\Academic\Domain\Actions\MarkAttendanceAction;
use Modules\Academic\Domain\Actions\RebuildAttendanceSummaryAction;
use Modules\Academic\Domain\Actions\RecordMarkingComplianceAction;
use Modules\Academic\Domain\DataObjects\AllocateClassData;
use Modules\Academic\Domain\DataObjects\AmendAttendanceRecordData;
use Modules\Academic\Domain\DataObjects\CreateAttendanceReasonCodeData;
use Modules\Academic\Domain\DataObjects\GenerateAttendanceSessionData;
use Modules\Academic\Domain\DataObjects\MarkAttendanceData;
use Modules\Academic\Domain\DataObjects\MarkAttendanceRecordInput;
use Modules\Academic\Domain\DataObjects\RebuildAttendanceSummaryData;
use Modules\Academic\Domain\DataObjects\RecordMarkingComplianceData;
use Modules\Academic\Domain\Exceptions\AttendanceSessionLockedException;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Comms\Domain\Actions\RegisterMessageGatewayAction;
use Modules\Comms\Domain\DataObjects\RegisterMessageGatewayData;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\Actions\LinkGuardianToStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\People\Models\TeacherAllocation;

/**
 * @return array{school: School, year: AcademicYear, term: Term, class: SchoolClass, section: SchoolSection, user: User}
 */
function aca04Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $section = SchoolSection::factory()->for($school)->create();
    $class = SchoolClass::factory()->for($school)->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    return ['school' => $school, 'year' => $year, 'term' => $term, 'class' => $class, 'section' => $section, 'user' => User::factory()->create()];
}

/**
 * @param  array<string, mixed>  $f
 */
function aca04Student(array $f): Student
{
    return app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        firstName: 'Panashe'.uniqid(),
        lastName: 'Ndlovu',
        dateOfBirth: now()->subYears(12),
        gender: 'female',
        enrolmentType: 'FULL_TIME',
        residency: 'DAY',
        sectionId: $f['section']->id,
        gradeLevelId: $f['class']->grade_level_id,
        entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id,
        skipDuplicateCheck: true,
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function aca04AllocateToClass(array $f, Student $student): void
{
    app(AllocateClassAction::class)->execute(new AllocateClassData(
        studentId: $student->id, classId: $f['class']->id, termId: $f['term']->id,
        allocatedByUserId: $f['user']->id, effectiveFrom: now()->subDays(3),
    ));
}

it('generates a session with expected_count from active class allocations (BR-ACA-04-002/003)', function (): void {
    $f = aca04Fixture();
    $studentA = aca04Student($f);
    aca04AllocateToClass($f, $studentA);

    $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'daily', classId: $f['class']->id,
    ));

    expect($session->expected_count)->toBe(1)
        ->and($session->status)->toBe('pending');
});

it('never infers an unmarked session as everyone present (BR-ACA-04-018)', function (): void {
    $f = aca04Fixture();

    $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'daily', classId: $f['class']->id,
    ));

    expect($session->status)->toBe('pending')
        ->and($session->present_count)->toBe(0);
});

it('marks a register, updates the session roll-up, and completes only once every learner is marked (BR-ACA-04-017)', function (): void {
    $f = aca04Fixture();
    $studentA = aca04Student($f);
    $studentB = aca04Student($f);
    aca04AllocateToClass($f, $studentA);
    aca04AllocateToClass($f, $studentB);

    $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'daily', classId: $f['class']->id,
    ));

    expect($session->expected_count)->toBe(2);

    $result = app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
        sessionId: $session->id,
        records: [new MarkAttendanceRecordInput(studentId: $studentA->id, status: 'present')],
        markedByUserId: $f['user']->id,
    ));

    expect($result->session->status)->toBe('partial')
        ->and($result->conflicts)->toBe([]);

    $result = app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
        sessionId: $session->id,
        records: [new MarkAttendanceRecordInput(studentId: $studentB->id, status: 'absent')],
        markedByUserId: $f['user']->id,
    ));

    expect($result->session->status)->toBe('completed')
        ->and($result->session->present_count)->toBe(1)
        ->and($result->session->absent_count)->toBe(1);
});

it('lets the first mark stand and reports a conflict on a genuinely different resubmission (AC-ACA-04-005)', function (): void {
    $f = aca04Fixture();
    $student = aca04Student($f);
    aca04AllocateToClass($f, $student);

    $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'daily', classId: $f['class']->id,
    ));

    app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
        sessionId: $session->id,
        records: [new MarkAttendanceRecordInput(studentId: $student->id, status: 'present', idempotencyKey: 'device-a-1')],
        markedByUserId: $f['user']->id,
    ));

    $result = app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
        sessionId: $session->id,
        records: [new MarkAttendanceRecordInput(studentId: $student->id, status: 'absent', idempotencyKey: 'device-b-1')],
        markedByUserId: $f['user']->id,
    ));

    expect($result->conflicts)->toBe([[
        'student_id' => $student->id, 'existing_status' => 'present', 'attempted_status' => 'absent',
    ]]);

    expect(AttendanceRecord::where('session_id', $session->id)->where('student_id', $student->id)->first()->status)->toBe('present');
});

it('treats a resubmission with the same idempotency key as a harmless replay, not a conflict', function (): void {
    $f = aca04Fixture();
    $student = aca04Student($f);
    aca04AllocateToClass($f, $student);

    $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'daily', classId: $f['class']->id,
    ));

    $input = new MarkAttendanceRecordInput(studentId: $student->id, status: 'present', idempotencyKey: 'sync-key-1');

    app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(sessionId: $session->id, records: [$input], markedByUserId: $f['user']->id));
    $result = app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(sessionId: $session->id, records: [$input], markedByUserId: $f['user']->id));

    expect($result->conflicts)->toBe([])
        ->and(AttendanceRecord::where('session_id', $session->id)->count())->toBe(1);
});

it('notifies the primary contact within the same request for an unexplained absence, exactly once per learner per day (BR-ACA-04-006/007)', function (): void {
    $f = aca04Fixture();
    $student = aca04Student($f);
    aca04AllocateToClass($f, $student);

    $guardian = Guardian::factory()->for($f['school'])->create();
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $student->id, guardianId: $guardian->id, relationship: 'mother',
        createdByUserId: $f['user']->id, isPrimaryContact: true,
    ));

    // Book I COM-01: an SMS notification only reaches 'sent' with an
    // active gateway configured — a school with none genuinely cannot
    // send SMS, so this fixture provisions one like a real school would.
    app(RegisterMessageGatewayAction::class)->execute(new RegisterMessageGatewayData(
        schoolId: $f['school']->id, channel: 'sms', driver: 'bulksms_zw', name: 'Test SMS Gateway',
        credentials: 'test-key', createdByUserId: $f['user']->id,
    ));

    $sessionMorning = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'period', classId: $f['class']->id, periodNumber: 1,
    ));
    $sessionAfternoon = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'period', classId: $f['class']->id, periodNumber: 2,
    ));

    app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
        sessionId: $sessionMorning->id, records: [new MarkAttendanceRecordInput(studentId: $student->id, status: 'absent')], markedByUserId: $f['user']->id,
    ));
    app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
        sessionId: $sessionAfternoon->id, records: [new MarkAttendanceRecordInput(studentId: $student->id, status: 'absent')], markedByUserId: $f['user']->id,
    ));

    $sent = Notification::where('notification_key', 'attendance.unexplained_absence')
        ->where('recipient_id', $guardian->id)
        ->where('status', 'sent')
        ->get();

    expect($sent)->toHaveCount(1);
});

it('suppresses the notification for a reason code flagged suppresses_notification (BR-ACA-04-007)', function (): void {
    $f = aca04Fixture();
    $student = aca04Student($f);
    aca04AllocateToClass($f, $student);

    $guardian = Guardian::factory()->for($f['school'])->create();
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $student->id, guardianId: $guardian->id, relationship: 'mother',
        createdByUserId: $f['user']->id, isPrimaryContact: true,
    ));

    $exeat = app(CreateAttendanceReasonCodeAction::class)->execute(new CreateAttendanceReasonCodeData(
        schoolId: $f['school']->id, code: 'EXEAT', name: 'Approved exeat', suppressesNotification: true,
    ));

    $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'daily', classId: $f['class']->id,
    ));

    app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
        sessionId: $session->id,
        records: [new MarkAttendanceRecordInput(studentId: $student->id, status: 'absent', reasonCodeId: $exeat->id)],
        markedByUserId: $f['user']->id,
    ));

    expect(Notification::where('notification_key', 'attendance.unexplained_absence')->where('recipient_id', $guardian->id)->exists())->toBeFalse();
});

it('preserves the original status when a mark is amended and refuses a bare status write (BR-ACA-04-008)', function (): void {
    $f = aca04Fixture();
    $student = aca04Student($f);
    aca04AllocateToClass($f, $student);

    $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'daily', classId: $f['class']->id,
    ));

    app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
        sessionId: $session->id, records: [new MarkAttendanceRecordInput(studentId: $student->id, status: 'absent')], markedByUserId: $f['user']->id,
    ));

    $record = AttendanceRecord::where('session_id', $session->id)->where('student_id', $student->id)->firstOrFail();

    expect(fn () => $record->update(['status' => 'present']))->toThrow(InvalidStateTransitionException::class);

    $amended = app(AmendAttendanceRecordAction::class)->execute(new AmendAttendanceRecordData(
        recordId: $record->id, newStatus: 'present', amendedByUserId: $f['user']->id, amendmentReason: 'Teacher corrected after checking the sick bay log.',
    ));

    expect($amended->status)->toBe('present')
        ->and($amended->original_status)->toBe('absent')
        ->and($amended->amendment_reason)->toBe('Teacher corrected after checking the sick bay log.');
});

it('refuses to amend a session past its lock window without the override, and allows it with the override (BR-ACA-04-009)', function (): void {
    $f = aca04Fixture();
    $student = aca04Student($f);
    aca04AllocateToClass($f, $student);

    $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now()->subDays(3), mode: 'daily', classId: $f['class']->id,
    ));
    $session->forceFill(['created_at' => now()->subHours(72)])->save();

    app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
        sessionId: $session->id, records: [new MarkAttendanceRecordInput(studentId: $student->id, status: 'present')], markedByUserId: $f['user']->id,
    ));

    $record = AttendanceRecord::where('session_id', $session->id)->where('student_id', $student->id)->firstOrFail();

    expect(fn () => app(AmendAttendanceRecordAction::class)->execute(new AmendAttendanceRecordData(
        recordId: $record->id, newStatus: 'absent', amendedByUserId: $f['user']->id, amendmentReason: 'Late correction.',
    )))->toThrow(AttendanceSessionLockedException::class);

    $amended = app(AmendAttendanceRecordAction::class)->execute(new AmendAttendanceRecordData(
        recordId: $record->id, newStatus: 'absent', amendedByUserId: $f['user']->id, amendmentReason: 'Late correction.', overrideLock: true,
    ));

    expect($amended->status)->toBe('absent');
});

it('rebuilds the attendance summary from source records and flags a chronic absentee (BR-ACA-04-011/013)', function (): void {
    $f = aca04Fixture();
    $student = aca04Student($f);
    aca04AllocateToClass($f, $student);

    foreach ([true, true, true, true, false] as $day => $isAbsent) {
        $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
            schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
            sessionDate: now()->addDays($day), mode: 'daily', classId: $f['class']->id,
        ));

        app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
            sessionId: $session->id,
            records: [new MarkAttendanceRecordInput(studentId: $student->id, status: $isAbsent ? 'absent' : 'present')],
            markedByUserId: $f['user']->id,
        ));
    }

    $summary = app(RebuildAttendanceSummaryAction::class)->execute(new RebuildAttendanceSummaryData(
        studentId: $student->id, termId: $f['term']->id,
    ));

    expect($summary->sessions_expected)->toBe(5)
        ->and($summary->present_count)->toBe(1)
        ->and($summary->absent_unauthorised)->toBe(4)
        ->and((float) $summary->attendance_percent)->toBe(20.0)
        ->and($summary->is_chronic_absentee)->toBeTrue();
});

it('excludes a counts_as_present reason from reducing the attendance percentage (BR-ACA-04-005)', function (): void {
    $f = aca04Fixture();
    $student = aca04Student($f);
    aca04AllocateToClass($f, $student);

    $sportsFixture = app(CreateAttendanceReasonCodeAction::class)->execute(new CreateAttendanceReasonCodeData(
        schoolId: $f['school']->id, code: 'SPORT', name: 'Sports fixture', countsAsPresent: true,
    ));

    $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'daily', classId: $f['class']->id,
    ));

    app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
        sessionId: $session->id,
        records: [new MarkAttendanceRecordInput(studentId: $student->id, status: 'absent', reasonCodeId: $sportsFixture->id)],
        markedByUserId: $f['user']->id,
    ));

    $summary = app(RebuildAttendanceSummaryAction::class)->execute(new RebuildAttendanceSummaryData(
        studentId: $student->id, termId: $f['term']->id,
    ));

    expect((float) $summary->attendance_percent)->toBe(100.0)
        ->and($summary->is_chronic_absentee)->toBeFalse();
});

it('records marking compliance for a class teacher and flags an unmarked register (BR-ACA-04-014)', function (): void {
    $f = aca04Fixture();
    $staff = Staff::factory()->for($f['school'])->create();

    TeacherAllocation::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'staff_id' => $staff->id, 'class_id' => $f['class']->id, 'is_class_teacher' => true, 'starts_on' => now()->subMonth(),
        'allocated_by' => $f['user']->id,
    ]);

    $marked = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'daily', classId: $f['class']->id,
    ));
    $student = aca04Student($f);
    aca04AllocateToClass($f, $student);
    app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
        sessionId: $marked->id, records: [new MarkAttendanceRecordInput(studentId: $student->id, status: 'present')], markedByUserId: $f['user']->id,
    ));

    $otherClass = SchoolClass::factory()->for($f['school'])->create();
    app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        sessionDate: now(), mode: 'daily', classId: $otherClass->id,
    ));

    $compliance = app(RecordMarkingComplianceAction::class)->execute(new RecordMarkingComplianceData(
        staffId: $staff->id, termId: $f['term']->id, sessionDate: now(),
    ));

    expect($compliance->expected_sessions)->toBe(1)
        ->and($compliance->marked_sessions)->toBe(1)
        ->and((float) $compliance->compliance_percent)->toBe(100.0);
});
