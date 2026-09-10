<?php

use App\Models\User;
use Modules\Academic\Domain\Actions\AllocateClassAction;
use Modules\Academic\Domain\Actions\AmendMarkAction;
use Modules\Academic\Domain\Actions\ComputeTermResultsAction;
use Modules\Academic\Domain\Actions\ComputeTermSubjectResultsAction;
use Modules\Academic\Domain\Actions\CreateAssessmentAction;
use Modules\Academic\Domain\Actions\CreateAssessmentTypeAction;
use Modules\Academic\Domain\Actions\CreateGradingScaleAction;
use Modules\Academic\Domain\Actions\EnterMarkAction;
use Modules\Academic\Domain\Actions\PublishAssessmentAction;
use Modules\Academic\Domain\Actions\RecomputeSubjectPositionsAction;
use Modules\Academic\Domain\Actions\RecomputeTermPositionsAction;
use Modules\Academic\Domain\Actions\SubmitAssessmentMarksAction;
use Modules\Academic\Domain\DataObjects\AllocateClassData;
use Modules\Academic\Domain\DataObjects\AmendMarkData;
use Modules\Academic\Domain\DataObjects\ComputeTermResultsData;
use Modules\Academic\Domain\DataObjects\ComputeTermSubjectResultsData;
use Modules\Academic\Domain\DataObjects\CreateAssessmentData;
use Modules\Academic\Domain\DataObjects\CreateAssessmentTypeData;
use Modules\Academic\Domain\DataObjects\CreateGradingScaleData;
use Modules\Academic\Domain\DataObjects\EnterMarkData;
use Modules\Academic\Domain\DataObjects\GradeBandInput;
use Modules\Academic\Domain\DataObjects\PublishAssessmentData;
use Modules\Academic\Domain\DataObjects\RecomputeSubjectPositionsData;
use Modules\Academic\Domain\DataObjects\RecomputeTermPositionsData;
use Modules\Academic\Domain\DataObjects\SubmitAssessmentMarksData;
use Modules\Academic\Domain\Exceptions\GradeBandGapOrOverlapException;
use Modules\Academic\Domain\Exceptions\MarkAmendmentRequiresApprovalException;
use Modules\Academic\Domain\Exceptions\MarkOutOfRangeException;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\AssessmentMarkVersion;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\GradingScale;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TermResult;
use Modules\Academic\Models\TermSubjectResult;
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
use Modules\People\Models\Student;

/**
 * @return array{school: School, year: AcademicYear, term: Term, class: SchoolClass, section: SchoolSection, framework: CurriculumFramework, subject: Subject, user: User}
 */
function aca05Fixture(int $courseworkWeightPercent = 40): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $class = SchoolClass::factory()->for($school)->create(['grade_level_id' => $gradeLevel->id]);
    $framework = CurriculumFramework::factory()->for($school)->create();
    $subject = Subject::factory()->for($school)->create([
        'framework_id' => $framework->id,
        'coursework_weight_percent' => $courseworkWeightPercent,
    ]);
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'class' => $class,
        'section' => $section, 'framework' => $framework, 'subject' => $subject, 'user' => $user,
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function aca05Student(array $f, string $firstName): Student
{
    $student = app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        firstName: $firstName,
        lastName: 'Moyo',
        dateOfBirth: now()->subYears(14),
        gender: 'male',
        enrolmentType: 'FULL_TIME',
        residency: 'DAY',
        sectionId: $f['section']->id,
        gradeLevelId: $f['class']->grade_level_id,
        entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id,
        skipDuplicateCheck: true,
    ));

    app(AllocateClassAction::class)->execute(new AllocateClassData(
        studentId: $student->id, classId: $f['class']->id, termId: $f['term']->id,
        allocatedByUserId: $f['user']->id, effectiveFrom: now()->subDays(3),
    ));

    return $student;
}

/**
 * @param  array<string, mixed>  $f
 * @return array{coursework: int, examination: int}
 */
function aca05AssessmentTypes(array $f): array
{
    $coursework = app(CreateAssessmentTypeAction::class)->execute(new CreateAssessmentTypeData(
        schoolId: $f['school']->id, code: 'TEST', name: 'Topic Test', category: 'coursework', defaultWeightPercent: 100,
    ));
    $examination = app(CreateAssessmentTypeAction::class)->execute(new CreateAssessmentTypeData(
        schoolId: $f['school']->id, code: 'EXAM', name: 'End of Term Exam', category: 'examination', defaultWeightPercent: 100,
    ));

    return ['coursework' => $coursework->id, 'examination' => $examination->id];
}

it('refuses a grading scale whose bands leave a gap (AC-ACA-05-008)', function (): void {
    $f = aca05Fixture();

    expect(fn () => app(CreateGradingScaleAction::class)->execute(new CreateGradingScaleData(
        schoolId: $f['school']->id, code: 'GAPPY', name: 'Gappy Scale', scaleType: 'percentage',
        bands: [
            new GradeBandInput(grade: 'FAIL', minPercent: 0, maxPercent: 49.5, isPass: false),
            new GradeBandInput(grade: 'PASS', minPercent: 50.0, maxPercent: 100, isPass: true),
        ],
    )))->toThrow(GradeBandGapOrOverlapException::class);
});

it('accepts a grading scale whose bands touch edge to edge across 0-100', function (): void {
    $f = aca05Fixture();

    $scale = app(CreateGradingScaleAction::class)->execute(new CreateGradingScaleData(
        schoolId: $f['school']->id, code: 'PF', name: 'Pass/Fail', scaleType: 'percentage',
        bands: [
            new GradeBandInput(grade: 'FAIL', minPercent: 0, maxPercent: 50, isPass: false),
            new GradeBandInput(grade: 'PASS', minPercent: 50, maxPercent: 100, isPass: true),
        ],
    ));

    expect($scale->bands()->count())->toBe(2)
        ->and($scale->bandFor(49.99)->grade)->toBe('FAIL')
        ->and($scale->bandFor(50.0)->grade)->toBe('PASS')
        ->and($scale->bandFor(100.0)->grade)->toBe('PASS');
});

it('refuses a mark that exceeds max_mark (BR-ACA-05-007)', function (): void {
    $f = aca05Fixture();
    $types = aca05AssessmentTypes($f);
    $student = aca05Student($f, 'Tanaka');

    $assessment = app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        assessmentTypeId: $types['coursework'], subjectId: $f['subject']->id, title: 'Test 1',
        maxMark: 50, weightPercent: 100, createdByUserId: $f['user']->id,
    ));

    expect(fn () => app(EnterMarkAction::class)->execute(new EnterMarkData(
        assessmentId: $assessment->id, studentId: $student->id, enteredByUserId: $f['user']->id, rawMark: 60,
    )))->toThrow(MarkOutOfRangeException::class);
});

it('excludes an absent-with-reason mark from the mean rather than counting it as zero (AC-ACA-05-002)', function (): void {
    $f = aca05Fixture();
    $types = aca05AssessmentTypes($f);
    $student = aca05Student($f, 'Rudo');

    $test1 = app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        assessmentTypeId: $types['coursework'], subjectId: $f['subject']->id, title: 'Test 1',
        maxMark: 100, weightPercent: 50, createdByUserId: $f['user']->id,
    ));
    $test2 = app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        assessmentTypeId: $types['coursework'], subjectId: $f['subject']->id, title: 'Test 2',
        maxMark: 100, weightPercent: 50, createdByUserId: $f['user']->id,
    ));

    app(EnterMarkAction::class)->execute(new EnterMarkData(
        assessmentId: $test1->id, studentId: $student->id, enteredByUserId: $f['user']->id,
        isAbsent: true, absenceReason: 'Sick',
    ));
    app(EnterMarkAction::class)->execute(new EnterMarkData(
        assessmentId: $test2->id, studentId: $student->id, enteredByUserId: $f['user']->id, rawMark: 80,
    ));

    foreach ([$test1, $test2] as $assessment) {
        app(SubmitAssessmentMarksAction::class)->execute(new SubmitAssessmentMarksData($assessment->id, $f['user']->id));
        app(PublishAssessmentAction::class)->execute(new PublishAssessmentData($assessment->id, $f['user']->id));
    }

    $result = app(ComputeTermSubjectResultsAction::class)->execute(new ComputeTermSubjectResultsData(
        studentId: $student->id, subjectId: $f['subject']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
    ));

    expect((float) $result->coursework_percent)->toBe(80.0);
});

it('computes final percent from the subject coursework weight, ranks the class, and aggregates term results', function (): void {
    $f = aca05Fixture(courseworkWeightPercent: 40);
    $types = aca05AssessmentTypes($f);

    app(CreateGradingScaleAction::class)->execute(new CreateGradingScaleData(
        schoolId: $f['school']->id, code: 'PF', name: 'Pass/Fail', scaleType: 'percentage',
        bands: [
            new GradeBandInput(grade: 'FAIL', minPercent: 0, maxPercent: 50, isPass: false),
            new GradeBandInput(grade: 'PASS', minPercent: 50, maxPercent: 100, isPass: true),
        ],
    ));
    $scale = GradingScale::where('school_id', $f['school']->id)->where('code', 'PF')->firstOrFail();
    $f['subject']->update(['grading_scale_id' => $scale->id]);

    $studentA = aca05Student($f, 'Anesu');
    $studentB = aca05Student($f, 'Blessing');

    $coursework = app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        assessmentTypeId: $types['coursework'], subjectId: $f['subject']->id, title: 'Test 1',
        maxMark: 100, weightPercent: 100, createdByUserId: $f['user']->id,
    ));
    $exam = app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        assessmentTypeId: $types['examination'], subjectId: $f['subject']->id, title: 'End of Term Exam',
        maxMark: 100, weightPercent: 100, createdByUserId: $f['user']->id,
    ));

    app(EnterMarkAction::class)->execute(new EnterMarkData($coursework->id, $studentA->id, $f['user']->id, rawMark: 80));
    app(EnterMarkAction::class)->execute(new EnterMarkData($exam->id, $studentA->id, $f['user']->id, rawMark: 90));
    app(EnterMarkAction::class)->execute(new EnterMarkData($coursework->id, $studentB->id, $f['user']->id, rawMark: 30));
    app(EnterMarkAction::class)->execute(new EnterMarkData($exam->id, $studentB->id, $f['user']->id, rawMark: 40));

    foreach ([$coursework, $exam] as $assessment) {
        app(SubmitAssessmentMarksAction::class)->execute(new SubmitAssessmentMarksData($assessment->id, $f['user']->id));
        app(PublishAssessmentAction::class)->execute(new PublishAssessmentData($assessment->id, $f['user']->id));
    }

    foreach ([$studentA, $studentB] as $student) {
        app(ComputeTermSubjectResultsAction::class)->execute(new ComputeTermSubjectResultsData(
            studentId: $student->id, subjectId: $f['subject']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        ));
    }

    app(RecomputeSubjectPositionsAction::class)->execute(new RecomputeSubjectPositionsData(
        schoolId: $f['school']->id, termId: $f['term']->id, subjectId: $f['subject']->id,
    ));

    $resultA = TermSubjectResult::where('student_id', $studentA->id)->where('term_id', $f['term']->id)->firstOrFail();
    $resultB = TermSubjectResult::where('student_id', $studentB->id)->where('term_id', $f['term']->id)->firstOrFail();

    // A: 80*0.4 + 90*0.6 = 86; B: 30*0.4 + 40*0.6 = 36
    expect((float) $resultA->final_percent)->toBe(86.0)
        ->and($resultA->grade)->toBe('PASS')
        ->and((float) $resultB->final_percent)->toBe(36.0)
        ->and($resultB->grade)->toBe('FAIL')
        ->and($resultA->class_position)->toBe(1)
        ->and($resultB->class_position)->toBe(2)
        ->and((float) $resultA->subject_average)->toBe(61.0);

    foreach ([$studentA, $studentB] as $student) {
        app(ComputeTermResultsAction::class)->execute(new ComputeTermResultsData($student->id, $f['term']->id));
    }

    app(RecomputeTermPositionsAction::class)->execute(new RecomputeTermPositionsData(
        schoolId: $f['school']->id, termId: $f['term']->id, classId: $f['class']->id,
    ));

    $termResultA = TermResult::where('student_id', $studentA->id)->firstOrFail();
    $termResultB = TermResult::where('student_id', $studentB->id)->firstOrFail();

    expect($termResultA->subjects_taken)->toBe(1)
        ->and($termResultA->subjects_passed)->toBe(1)
        ->and((float) $termResultA->average_percent)->toBe(86.0)
        ->and($termResultA->class_position)->toBe(1)
        ->and($termResultB->subjects_passed)->toBe(0)
        ->and($termResultB->class_position)->toBe(2)
        ->and($termResultB->promotion_recommendation)->toBe('repeat');
});

it('requires approval to amend a published mark, and recomputes positions for the whole class (AC-ACA-05-003)', function (): void {
    $f = aca05Fixture(courseworkWeightPercent: 0);
    $types = aca05AssessmentTypes($f);
    $studentA = aca05Student($f, 'Chipo');
    $studentB = aca05Student($f, 'Farai');

    $exam = app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        assessmentTypeId: $types['examination'], subjectId: $f['subject']->id, title: 'End of Term Exam',
        maxMark: 100, weightPercent: 100, createdByUserId: $f['user']->id,
    ));

    app(EnterMarkAction::class)->execute(new EnterMarkData($exam->id, $studentA->id, $f['user']->id, rawMark: 80));
    app(EnterMarkAction::class)->execute(new EnterMarkData($exam->id, $studentB->id, $f['user']->id, rawMark: 40));

    app(SubmitAssessmentMarksAction::class)->execute(new SubmitAssessmentMarksData($exam->id, $f['user']->id));
    app(PublishAssessmentAction::class)->execute(new PublishAssessmentData($exam->id, $f['user']->id));

    foreach ([$studentA, $studentB] as $student) {
        app(ComputeTermSubjectResultsAction::class)->execute(new ComputeTermSubjectResultsData(
            studentId: $student->id, subjectId: $f['subject']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        ));
    }
    app(RecomputeSubjectPositionsAction::class)->execute(new RecomputeSubjectPositionsData(
        schoolId: $f['school']->id, termId: $f['term']->id, subjectId: $f['subject']->id,
    ));

    $before = TermSubjectResult::where('student_id', $studentA->id)->firstOrFail();
    expect($before->class_position)->toBe(1);

    expect(fn () => app(AmendMarkAction::class)->execute(new AmendMarkData(
        assessmentId: $exam->id, studentId: $studentB->id, changedByUserId: $f['user']->id,
        changeReason: 'Re-marked after a moderation review found a tallying error.', rawMark: 95,
    )))->toThrow(MarkAmendmentRequiresApprovalException::class);

    $version = app(AmendMarkAction::class)->execute(new AmendMarkData(
        assessmentId: $exam->id, studentId: $studentB->id, changedByUserId: $f['user']->id,
        changeReason: 'Re-marked after a moderation review found a tallying error.', rawMark: 95, approved: true,
    ));

    expect($version->was_published)->toBeTrue()
        ->and((float) $version->raw_mark)->toBe(40.0);

    $mark = AssessmentMark::where('assessment_id', $exam->id)->where('student_id', $studentB->id)->firstOrFail();
    expect((float) $mark->raw_mark)->toBe(95.0)
        ->and($mark->version)->toBe(2);

    expect(AssessmentMarkVersion::where('assessment_id', $exam->id)->where('student_id', $studentB->id)->count())->toBe(1);

    $afterA = TermSubjectResult::where('student_id', $studentA->id)->firstOrFail();
    $afterB = TermSubjectResult::where('student_id', $studentB->id)->firstOrFail();

    expect($afterB->class_position)->toBe(1)
        ->and($afterA->class_position)->toBe(2);
});
