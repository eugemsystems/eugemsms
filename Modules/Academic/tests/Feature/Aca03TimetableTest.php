<?php

use App\Models\User;
use Modules\Academic\Domain\Actions\AllocateClassAction;
use Modules\Academic\Domain\Actions\AssignSubstituteCoverAction;
use Modules\Academic\Domain\Actions\AssignToTeachingGroupAction;
use Modules\Academic\Domain\Actions\CreatePeriodStructureAction;
use Modules\Academic\Domain\Actions\CreateSubstitutionsForLeaveAction;
use Modules\Academic\Domain\Actions\CreateTeachingGroupAction;
use Modules\Academic\Domain\Actions\CreateTimetableExceptionAction;
use Modules\Academic\Domain\Actions\CreateTimetableSlotAction;
use Modules\Academic\Domain\Actions\CreateVenueAction;
use Modules\Academic\Domain\Actions\GenerateAttendanceSessionsFromTimetableAction;
use Modules\Academic\Domain\Actions\GenerateTimetableAction;
use Modules\Academic\Domain\Actions\PublishTimetableAction;
use Modules\Academic\Domain\Actions\SuggestCoverAction;
use Modules\Academic\Domain\DataObjects\AllocateClassData;
use Modules\Academic\Domain\DataObjects\AssignSubstituteCoverData;
use Modules\Academic\Domain\DataObjects\AssignToTeachingGroupData;
use Modules\Academic\Domain\DataObjects\CreatePeriodStructureData;
use Modules\Academic\Domain\DataObjects\CreateSubstitutionsForLeaveData;
use Modules\Academic\Domain\DataObjects\CreateTeachingGroupData;
use Modules\Academic\Domain\DataObjects\CreateTimetableExceptionData;
use Modules\Academic\Domain\DataObjects\CreateTimetableSlotData;
use Modules\Academic\Domain\DataObjects\CreateVenueData;
use Modules\Academic\Domain\DataObjects\GenerateAttendanceSessionsFromTimetableData;
use Modules\Academic\Domain\DataObjects\GenerateTimetableData;
use Modules\Academic\Domain\DataObjects\PeriodSlotInput;
use Modules\Academic\Domain\DataObjects\PublishTimetableData;
use Modules\Academic\Domain\DataObjects\SuggestCoverData;
use Modules\Academic\Domain\Exceptions\TimetableSlotClashException;
use Modules\Academic\Models\AttendanceSession;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\People\Models\TeacherAllocation;

/**
 * @return array{school: School, year: AcademicYear, term: Term, section: SchoolSection, gradeLevel: GradeLevel, framework: CurriculumFramework, user: User}
 */
function aca03Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $termStart = now()->subDays(10)->startOfDay();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'is_current' => true,
        'starts_on' => $termStart,
        'ends_on' => $termStart->copy()->addDays(90),
    ]);
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $framework = CurriculumFramework::factory()->for($school)->create();
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    return compact('school', 'year', 'term', 'section', 'gradeLevel', 'framework', 'user');
}

/**
 * @param  array<string, mixed>  $f
 */
function aca03Structure(array $f, int $cycleDays = 5): PeriodStructure
{
    $slots = [];

    for ($day = 1; $day <= $cycleDays; $day++) {
        for ($period = 1; $period <= 2; $period++) {
            $slots[] = new PeriodSlotInput(
                cycleDay: $day, periodNumber: $period, label: "Period {$period}", slotType: 'teaching',
                startsAt: '07:30', endsAt: '08:10', durationMinutes: 40,
            );
        }
    }

    return app(CreatePeriodStructureAction::class)->execute(new CreatePeriodStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Test Structure',
        cycleType: $cycleDays === 1 ? 'weekly' : 'weekly', cycleDays: $cycleDays,
        dayLabels: array_map(fn (int $d): string => "Day {$d}", range(1, $cycleDays)),
        slots: $slots,
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function aca03Student(array $f, string $firstName): Student
{
    return app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        firstName: $firstName, lastName: 'Moyo', dateOfBirth: now()->subYears(14), gender: 'male',
        enrolmentType: 'FULL_TIME', residency: 'DAY', sectionId: $f['section']->id,
        gradeLevelId: $f['gradeLevel']->id, entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id, skipDuplicateCheck: true,
    ));
}

it('builds a period structure with its complete slot set', function (): void {
    $f = aca03Fixture();
    $structure = aca03Structure($f, cycleDays: 5);

    expect($structure->slots()->count())->toBe(10);
});

it('refuses a teacher clash, a venue clash, and a learner clash, but allows a genuinely free slot (BR-ACA-03-006/007)', function (): void {
    $f = aca03Fixture();
    $structure = aca03Structure($f);
    $timetable = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'structure_id' => $structure->id, 'created_by' => $f['user']->id,
    ]);
    $periodSlot = $structure->slots()->where('cycle_day', 1)->where('period_number', 1)->first();

    $venue = app(CreateVenueAction::class)->execute(new CreateVenueData($f['school']->id, 'R1', 'Room 1', 'classroom', 40));
    $venue2 = app(CreateVenueAction::class)->execute(new CreateVenueData($f['school']->id, 'R2', 'Room 2', 'classroom', 40));

    $classA = SchoolClass::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id]);
    $classB = SchoolClass::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id]);
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $staffA = Staff::factory()->for($f['school'])->create();
    $staffB = Staff::factory()->for($f['school'])->create();

    $student = aca03Student($f, 'Learner');
    app(AllocateClassAction::class)->execute(new AllocateClassData(
        studentId: $student->id, classId: $classA->id, termId: $f['term']->id,
        allocatedByUserId: $f['user']->id, effectiveFrom: now()->subDays(3),
    ));

    $group = app(CreateTeachingGroupAction::class)->execute(new CreateTeachingGroupData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        subjectId: $subject->id, gradeLevelId: $f['gradeLevel']->id, code: 'SET1', name: 'Set 1',
    ));
    app(AssignToTeachingGroupAction::class)->execute(new AssignToTeachingGroupData(studentId: $student->id, teachingGroupId: $group->id));

    $first = app(CreateTimetableSlotAction::class)->execute(new CreateTimetableSlotData(
        timetableId: $timetable->id, termId: $f['term']->id, periodSlotId: $periodSlot->id,
        cycleDay: 1, periodNumber: 1, subjectId: $subject->id, staffId: $staffA->id,
        classId: $classA->id, venueId: $venue->id,
    ));

    expect(fn () => app(CreateTimetableSlotAction::class)->execute(new CreateTimetableSlotData(
        timetableId: $timetable->id, termId: $f['term']->id, periodSlotId: $periodSlot->id,
        cycleDay: 1, periodNumber: 1, subjectId: $subject->id, staffId: $staffA->id,
        classId: $classB->id, venueId: $venue2->id,
    )))->toThrow(TimetableSlotClashException::class);

    expect(fn () => app(CreateTimetableSlotAction::class)->execute(new CreateTimetableSlotData(
        timetableId: $timetable->id, termId: $f['term']->id, periodSlotId: $periodSlot->id,
        cycleDay: 1, periodNumber: 1, subjectId: $subject->id, staffId: $staffB->id,
        classId: $classB->id, venueId: $venue->id,
    )))->toThrow(TimetableSlotClashException::class);

    expect(fn () => app(CreateTimetableSlotAction::class)->execute(new CreateTimetableSlotData(
        timetableId: $timetable->id, termId: $f['term']->id, periodSlotId: $periodSlot->id,
        cycleDay: 1, periodNumber: 1, subjectId: $subject->id, staffId: $staffB->id,
        teachingGroupId: $group->id, venueId: $venue2->id,
    )))->toThrow(TimetableSlotClashException::class);

    $free = app(CreateTimetableSlotAction::class)->execute(new CreateTimetableSlotData(
        timetableId: $timetable->id, termId: $f['term']->id, periodSlotId: $periodSlot->id,
        cycleDay: 1, periodNumber: 1, subjectId: $subject->id, staffId: $staffB->id,
        classId: $classB->id, venueId: $venue2->id,
    ));

    expect(TimetableSlot::where('timetable_id', $timetable->id)->count())->toBe(2)
        ->and($free->id)->not->toBe($first->id);
});

it('generates a timetable with zero hard violations and honestly reports what could not be placed', function (): void {
    $f = aca03Fixture();
    $structure = aca03Structure($f, cycleDays: 5); // 10 teachable slots

    $timetable = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'structure_id' => $structure->id, 'created_by' => $f['user']->id,
    ]);

    $classA = SchoolClass::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id]);
    $subjectA = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $staffA = Staff::factory()->for($f['school'])->create();

    TeacherAllocation::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'staff_id' => $staffA->id, 'subject_id' => $subjectA->id, 'class_id' => $classA->id,
        'weekly_periods' => 3, 'allocated_by' => $f['user']->id,
    ]);

    $run = app(GenerateTimetableAction::class)->execute(new GenerateTimetableData(
        timetableId: $timetable->id, requestedByUserId: $f['user']->id,
    ));

    expect($run->status)->toBe('completed')
        ->and($run->hard_violations)->toBe(0)
        ->and($run->unplaced_requirements)->toBe([])
        ->and(TimetableSlot::where('timetable_id', $timetable->id)->count())->toBe(3)
        ->and($timetable->fresh()->status)->toBe('generated');
});

it('reports an unplaced requirement honestly rather than force-fitting or silently dropping it (AC-ACA-03-002)', function (): void {
    $f = aca03Fixture();
    $structure = aca03Structure($f, cycleDays: 1); // only 2 teachable slots exist all week

    $timetable = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'structure_id' => $structure->id, 'created_by' => $f['user']->id,
    ]);

    $classA = SchoolClass::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id]);
    $subjectA = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $staffA = Staff::factory()->for($f['school'])->create();

    TeacherAllocation::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'staff_id' => $staffA->id, 'subject_id' => $subjectA->id, 'class_id' => $classA->id,
        'weekly_periods' => 5, 'allocated_by' => $f['user']->id,
    ]);

    $run = app(GenerateTimetableAction::class)->execute(new GenerateTimetableData(
        timetableId: $timetable->id, requestedByUserId: $f['user']->id,
    ));

    expect($run->unplaced_requirements)->toHaveCount(1)
        ->and($run->unplaced_requirements[0]['periods_requested'])->toBe(5)
        ->and($run->unplaced_requirements[0]['periods_placed'])->toBe(2)
        ->and(TimetableSlot::where('timetable_id', $timetable->id)->count())->toBe(2);
});

it('refuses to publish while a hard clash exists, and supersedes the prior published version on success', function (): void {
    $f = aca03Fixture();
    $structure = aca03Structure($f);
    $periodSlot = $structure->slots()->where('cycle_day', 1)->where('period_number', 1)->first();
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $staff = Staff::factory()->for($f['school'])->create();

    $timetableWithClash = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'structure_id' => $structure->id, 'created_by' => $f['user']->id,
    ]);

    // Bypass the guarded action to simulate bad existing data.
    TimetableSlot::factory()->count(2)->create([
        'school_id' => $f['school']->id, 'timetable_id' => $timetableWithClash->id, 'term_id' => $f['term']->id,
        'period_slot_id' => $periodSlot->id, 'cycle_day' => 1, 'period_number' => 1,
        'subject_id' => $subject->id, 'staff_id' => $staff->id,
    ]);

    expect(fn () => app(PublishTimetableAction::class)->execute(new PublishTimetableData(
        timetableId: $timetableWithClash->id, publishedByUserId: $f['user']->id,
    )))->toThrow(TimetableSlotClashException::class);

    $cleanTimetable = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'structure_id' => $structure->id, 'created_by' => $f['user']->id,
    ]);
    $firstPublished = app(PublishTimetableAction::class)->execute(new PublishTimetableData(
        timetableId: $cleanTimetable->id, publishedByUserId: $f['user']->id,
    ));
    expect($firstPublished->status)->toBe('published');

    $secondTimetable = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'structure_id' => $structure->id, 'created_by' => $f['user']->id, 'version' => 2,
    ]);
    app(PublishTimetableAction::class)->execute(new PublishTimetableData(
        timetableId: $secondTimetable->id, publishedByUserId: $f['user']->id,
    ));

    expect($firstPublished->fresh()->status)->toBe('superseded');
});

it('generates attendance sessions idempotently from a published timetable, honouring a no_lessons exception (AC-ACA-03-005/010)', function (): void {
    $f = aca03Fixture();
    $structure = aca03Structure($f, cycleDays: 1); // every teaching day is cycle day 1

    $timetable = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'structure_id' => $structure->id, 'created_by' => $f['user']->id,
    ]);

    $classA = SchoolClass::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id]);
    $subjectA = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $staffA = Staff::factory()->for($f['school'])->create();

    TeacherAllocation::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'staff_id' => $staffA->id, 'subject_id' => $subjectA->id, 'class_id' => $classA->id,
        'weekly_periods' => 1, 'allocated_by' => $f['user']->id,
    ]);

    app(GenerateTimetableAction::class)->execute(new GenerateTimetableData(timetableId: $timetable->id, requestedByUserId: $f['user']->id));
    app(PublishTimetableAction::class)->execute(new PublishTimetableData(timetableId: $timetable->id, publishedByUserId: $f['user']->id));

    $exceptionDate = now()->addDays(2);

    while ($exceptionDate->isWeekend()) {
        $exceptionDate = $exceptionDate->addDay();
    }

    app(CreateTimetableExceptionAction::class)->execute(new CreateTimetableExceptionData(
        schoolId: $f['school']->id, termId: $f['term']->id, exceptionDate: $exceptionDate,
        exceptionType: 'no_lessons', affectedScope: 'whole_school', reason: 'Public holiday',
        createdByUserId: $f['user']->id, suppressesAttendance: true,
    ));

    $result = app(GenerateAttendanceSessionsFromTimetableAction::class)->execute(new GenerateAttendanceSessionsFromTimetableData(
        timetableId: $timetable->id, fromDate: now(), toDate: now()->addDays(6),
    ));

    expect($result['created'])->toBeGreaterThan(0)
        ->and(AttendanceSession::whereDate('session_date', $exceptionDate->toDateString())->exists())->toBeFalse();

    $rerun = app(GenerateAttendanceSessionsFromTimetableAction::class)->execute(new GenerateAttendanceSessionsFromTimetableData(
        timetableId: $timetable->id, fromDate: now(), toDate: now()->addDays(6),
    ));

    expect($rerun['created'])->toBe(0)
        ->and($rerun['skipped'])->toBe($result['created']);
});

it('creates pending cover substitutions for a teaching slot and assigns cover, moving the attendance session staff (AC-ACA-03-006/007)', function (): void {
    $f = aca03Fixture();
    $structure = aca03Structure($f, cycleDays: 1);

    $timetable = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'structure_id' => $structure->id, 'created_by' => $f['user']->id,
    ]);

    $classA = SchoolClass::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id]);
    $subjectA = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $absentStaff = Staff::factory()->for($f['school'])->create(['is_teaching' => true]);
    $freeStaff = Staff::factory()->for($f['school'])->create(['is_teaching' => true]);

    TeacherAllocation::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'staff_id' => $absentStaff->id, 'subject_id' => $subjectA->id, 'class_id' => $classA->id,
        'weekly_periods' => 1, 'allocated_by' => $f['user']->id,
    ]);

    app(GenerateTimetableAction::class)->execute(new GenerateTimetableData(timetableId: $timetable->id, requestedByUserId: $f['user']->id));
    app(PublishTimetableAction::class)->execute(new PublishTimetableData(timetableId: $timetable->id, publishedByUserId: $f['user']->id));

    app(GenerateAttendanceSessionsFromTimetableAction::class)->execute(new GenerateAttendanceSessionsFromTimetableData(
        timetableId: $timetable->id, fromDate: now(), toDate: now()->addDays(6),
    ));

    $created = app(CreateSubstitutionsForLeaveAction::class)->execute(new CreateSubstitutionsForLeaveData(
        staffId: $absentStaff->id, fromDate: now(), toDate: now()->addDays(6), reason: 'sick',
    ));

    expect($created->isNotEmpty())->toBeTrue();
    $substitution = $created->first();
    expect($substitution->status)->toBe('pending');

    $suggestions = app(SuggestCoverAction::class)->execute(new SuggestCoverData($substitution->id));
    expect($suggestions)->toContain($freeStaff->id);

    $assigned = app(AssignSubstituteCoverAction::class)->execute(new AssignSubstituteCoverData(
        substitutionId: $substitution->id, coverStaffId: $freeStaff->id, assignedByUserId: $f['user']->id,
    ));

    expect($assigned->status)->toBe('assigned')
        ->and($assigned->cover_staff_id)->toBe($freeStaff->id);

    $session = AttendanceSession::where('timetable_slot_id', $substitution->timetable_slot_id)
        ->whereDate('session_date', $substitution->substitution_date->toDateString())
        ->first();

    expect($session)->not->toBeNull()
        ->and($session->staff_id)->toBe($freeStaff->id);
});
