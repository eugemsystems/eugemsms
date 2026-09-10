<?php

use App\Models\User;
use Modules\Academic\Domain\Actions\ApproveProjectBriefAction;
use Modules\Academic\Domain\Actions\CreateAssessmentInstrumentAction;
use Modules\Academic\Domain\Actions\CreateProjectBriefAction;
use Modules\Academic\Domain\Actions\CreateProjectRubricAction;
use Modules\Academic\Domain\Actions\IssueProjectBriefAction;
use Modules\Academic\Domain\Actions\MarkProjectAction;
use Modules\Academic\Domain\Actions\ModerateProjectAction;
use Modules\Academic\Domain\Actions\VerifyProjectAction;
use Modules\Academic\Domain\DataObjects\ApproveProjectBriefData;
use Modules\Academic\Domain\DataObjects\CreateAssessmentInstrumentData;
use Modules\Academic\Domain\DataObjects\CreateProjectBriefData;
use Modules\Academic\Domain\DataObjects\CreateProjectRubricData;
use Modules\Academic\Domain\DataObjects\CriterionMarkInput;
use Modules\Academic\Domain\DataObjects\IssueProjectBriefData;
use Modules\Academic\Domain\DataObjects\MarkProjectData;
use Modules\Academic\Domain\DataObjects\ModerateProjectData;
use Modules\Academic\Domain\DataObjects\RubricCriterionInput;
use Modules\Academic\Domain\DataObjects\VerifyProjectData;
use Modules\Academic\Domain\Events\SubjectEnrolmentAdded;
use Modules\Academic\Domain\Events\SubjectEnrolmentDropped;
use Modules\Academic\Domain\Exceptions\DuplicateProjectBriefException;
use Modules\Academic\Domain\Exceptions\RubricWeightMismatchException;
use Modules\Academic\Domain\Support\ContinuousAssessmentProvider;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\LegacyCalaRecord;
use Modules\Academic\Models\ProjectRubric;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectEnrolmentChange;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use RuntimeException;

/**
 * @return array{school: School, year: AcademicYear, term: Term, gradeLevel: GradeLevel, framework: CurriculumFramework, subject: Subject, user: User, staff: Staff}
 */
function aca06Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $framework = CurriculumFramework::factory()->for($school)->create(['continuous_assessment_model' => 'sbp']);
    $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id, 'requires_sbp' => true]);
    $user = User::factory()->create();
    $staff = Staff::factory()->for($school)->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    return compact('school', 'year', 'term', 'gradeLevel', 'framework', 'subject', 'user', 'staff');
}

/**
 * @param  array<string, mixed>  $f
 */
function aca06Student(array $f, string $firstName): Student
{
    return app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        firstName: $firstName, lastName: 'Ncube', dateOfBirth: now()->subYears(14), gender: 'female',
        enrolmentType: 'FULL_TIME', residency: 'DAY', sectionId: $f['gradeLevel']->section_id,
        gradeLevelId: $f['gradeLevel']->id, entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id, skipDuplicateCheck: true,
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function aca06Rubric(array $f): ProjectRubric
{
    return app(CreateProjectRubricAction::class)->execute(new CreateProjectRubricData(
        schoolId: $f['school']->id,
        name: 'SBP Generic Rubric',
        criteria: [
            new RubricCriterionInput('Research & Planning', 25.0, 25.0, [['level' => 'Good', 'min_mark' => 15, 'descriptor' => 'x']]),
            new RubricCriterionInput('Execution', 50.0, 50.0, [['level' => 'Good', 'min_mark' => 30, 'descriptor' => 'x']]),
            new RubricCriterionInput('Presentation', 25.0, 25.0, [['level' => 'Good', 'min_mark' => 15, 'descriptor' => 'x']]),
        ],
    ));
}

it('refuses a rubric whose criterion weights do not total 100%', function (): void {
    $f = aca06Fixture();

    expect(fn () => app(CreateProjectRubricAction::class)->execute(new CreateProjectRubricData(
        schoolId: $f['school']->id,
        name: 'Broken Rubric',
        criteria: [
            new RubricCriterionInput('Research', 25.0, 25.0, []),
            new RubricCriterionInput('Execution', 50.0, 50.0, []),
        ],
    )))->toThrow(RubricWeightMismatchException::class);
});

it('issues a brief and creates a project for every actively enrolled learner', function (): void {
    $f = aca06Fixture();
    $rubric = aca06Rubric($f);
    $instrument = app(CreateAssessmentInstrumentAction::class)->execute(new CreateAssessmentInstrumentData(
        schoolId: $f['school']->id, frameworkId: $f['framework']->id, code: 'SBP', name: 'School-Based Project',
        defaultWeightPercent: 30.0,
    ));

    $enrolled = aca06Student($f, 'Enrolled');
    $notEnrolled = aca06Student($f, 'NotEnrolled');

    LearnerSubjectEnrolment::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $enrolled->id, 'subject_id' => $f['subject']->id, 'status' => 'active', 'added_by' => $f['user']->id,
    ]);

    $brief = app(CreateProjectBriefAction::class)->execute(new CreateProjectBriefData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, instrumentId: $instrument->id,
        subjectId: $f['subject']->id, gradeLevelId: $f['gradeLevel']->id, title: 'Water Access Project',
        description: 'Investigate local water access.', deliverables: ['Report'],
        startsOn: now(), dueOn: now()->addWeeks(6), maxMark: 100.0, rubricId: $rubric->id, createdBy: $f['user']->id,
    ));

    app(ApproveProjectBriefAction::class)->execute(new ApproveProjectBriefData(briefId: $brief->id, approvedByStaffId: $f['staff']->id));
    app(IssueProjectBriefAction::class)->execute(new IssueProjectBriefData(briefId: $brief->id));

    expect(LearnerProject::where('brief_id', $brief->id)->where('student_id', $enrolled->id)->exists())->toBeTrue()
        ->and(LearnerProject::where('brief_id', $brief->id)->where('student_id', $notEnrolled->id)->exists())->toBeFalse();
});

it('refuses a second brief for the same subject and level without an override', function (): void {
    $f = aca06Fixture();
    $rubric = aca06Rubric($f);
    $instrument = app(CreateAssessmentInstrumentAction::class)->execute(new CreateAssessmentInstrumentData(
        schoolId: $f['school']->id, frameworkId: $f['framework']->id, code: 'SBP', name: 'School-Based Project',
    ));

    $briefData = new CreateProjectBriefData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, instrumentId: $instrument->id,
        subjectId: $f['subject']->id, gradeLevelId: $f['gradeLevel']->id, title: 'First Project',
        description: 'First.', deliverables: ['Report'], startsOn: now(), dueOn: now()->addWeeks(6),
        maxMark: 100.0, rubricId: $rubric->id, createdBy: $f['user']->id,
    );
    app(CreateProjectBriefAction::class)->execute($briefData);

    $second = new CreateProjectBriefData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, instrumentId: $instrument->id,
        subjectId: $f['subject']->id, gradeLevelId: $f['gradeLevel']->id, title: 'Second Project',
        description: 'Second.', deliverables: ['Report'], startsOn: now(), dueOn: now()->addWeeks(6),
        maxMark: 100.0, rubricId: $rubric->id, createdBy: $f['user']->id,
    );

    expect(fn () => app(CreateProjectBriefAction::class)->execute($second))->toThrow(DuplicateProjectBriefException::class);
});

it('auto-creates a project for a learner who enrols after the brief is issued', function (): void {
    $f = aca06Fixture();
    $rubric = aca06Rubric($f);
    $instrument = app(CreateAssessmentInstrumentAction::class)->execute(new CreateAssessmentInstrumentData(
        schoolId: $f['school']->id, frameworkId: $f['framework']->id, code: 'SBP', name: 'School-Based Project',
    ));
    $brief = app(CreateProjectBriefAction::class)->execute(new CreateProjectBriefData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, instrumentId: $instrument->id,
        subjectId: $f['subject']->id, gradeLevelId: $f['gradeLevel']->id, title: 'Late Enrolment Project',
        description: 'x', deliverables: ['Report'], startsOn: now(), dueOn: now()->addWeeks(6),
        maxMark: 100.0, rubricId: $rubric->id, createdBy: $f['user']->id,
    ));
    app(ApproveProjectBriefAction::class)->execute(new ApproveProjectBriefData(briefId: $brief->id, approvedByStaffId: $f['staff']->id));
    app(IssueProjectBriefAction::class)->execute(new IssueProjectBriefData(briefId: $brief->id));

    $lateStudent = aca06Student($f, 'LateJoiner');
    $enrolment = LearnerSubjectEnrolment::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $lateStudent->id, 'subject_id' => $f['subject']->id, 'status' => 'active', 'added_by' => $f['user']->id,
    ]);
    $change = SubjectEnrolmentChange::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $lateStudent->id, 'term_id' => $f['term']->id,
        'subject_id' => $f['subject']->id, 'change_type' => 'added', 'changed_by' => $f['user']->id,
    ]);

    event(new SubjectEnrolmentAdded($enrolment, $change));

    expect(LearnerProject::where('brief_id', $brief->id)->where('student_id', $lateStudent->id)->exists())->toBeTrue();
});

it('exempts, never deletes, a learner project when the subject is dropped', function (): void {
    $f = aca06Fixture();
    $rubric = aca06Rubric($f);
    $instrument = app(CreateAssessmentInstrumentAction::class)->execute(new CreateAssessmentInstrumentData(
        schoolId: $f['school']->id, frameworkId: $f['framework']->id, code: 'SBP', name: 'School-Based Project',
    ));
    $student = aca06Student($f, 'Dropper');
    LearnerSubjectEnrolment::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'subject_id' => $f['subject']->id, 'status' => 'active', 'added_by' => $f['user']->id,
    ]);
    $brief = app(CreateProjectBriefAction::class)->execute(new CreateProjectBriefData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, instrumentId: $instrument->id,
        subjectId: $f['subject']->id, gradeLevelId: $f['gradeLevel']->id, title: 'Drop Test Project',
        description: 'x', deliverables: ['Report'], startsOn: now(), dueOn: now()->addWeeks(6),
        maxMark: 100.0, rubricId: $rubric->id, createdBy: $f['user']->id,
    ));
    app(ApproveProjectBriefAction::class)->execute(new ApproveProjectBriefData(briefId: $brief->id, approvedByStaffId: $f['staff']->id));
    app(IssueProjectBriefAction::class)->execute(new IssueProjectBriefData(briefId: $brief->id));

    $learnerProject = LearnerProject::where('brief_id', $brief->id)->where('student_id', $student->id)->firstOrFail();

    $enrolment = LearnerSubjectEnrolment::where('student_id', $student->id)->where('subject_id', $f['subject']->id)->firstOrFail();
    $change = SubjectEnrolmentChange::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $student->id, 'term_id' => $f['term']->id,
        'subject_id' => $f['subject']->id, 'change_type' => 'dropped', 'reason' => 'Timetable clash',
        'changed_by' => $f['user']->id,
    ]);

    event(new SubjectEnrolmentDropped($enrolment, $change));

    $learnerProject->refresh();
    expect($learnerProject->status)->toBe('exempt')
        ->and($learnerProject->exemption_reason)->toContain('Timetable clash')
        ->and(LearnerProject::find($learnerProject->id))->not->toBeNull();
});

it('marks, moderates and verifies a project, and only a verified outcome counts toward the final mark', function (): void {
    $f = aca06Fixture();
    $rubric = aca06Rubric($f);
    $instrument = app(CreateAssessmentInstrumentAction::class)->execute(new CreateAssessmentInstrumentData(
        schoolId: $f['school']->id, frameworkId: $f['framework']->id, code: 'SBP', name: 'School-Based Project',
    ));
    $student = aca06Student($f, 'Marked');
    LearnerSubjectEnrolment::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'subject_id' => $f['subject']->id, 'status' => 'active', 'added_by' => $f['user']->id,
    ]);
    $brief = app(CreateProjectBriefAction::class)->execute(new CreateProjectBriefData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, instrumentId: $instrument->id,
        subjectId: $f['subject']->id, gradeLevelId: $f['gradeLevel']->id, title: 'Marking Flow Project',
        description: 'x', deliverables: ['Report'], startsOn: now(), dueOn: now()->addWeeks(6),
        maxMark: 100.0, rubricId: $rubric->id, createdBy: $f['user']->id,
    ));
    app(ApproveProjectBriefAction::class)->execute(new ApproveProjectBriefData(briefId: $brief->id, approvedByStaffId: $f['staff']->id));
    app(IssueProjectBriefAction::class)->execute(new IssueProjectBriefData(briefId: $brief->id));

    $learnerProject = LearnerProject::where('brief_id', $brief->id)->where('student_id', $student->id)->firstOrFail();
    $learnerProject->update(['status' => 'submitted']);

    app(MarkProjectAction::class)->execute(new MarkProjectData(
        learnerProjectId: $learnerProject->id, markerStaffId: $f['staff']->id,
        criterionMarks: [
            new CriterionMarkInput('Research & Planning', 20.0),
            new CriterionMarkInput('Execution', 40.0),
            new CriterionMarkInput('Presentation', 20.0),
        ],
    ));

    $provider = app(ContinuousAssessmentProvider::class);
    $outcomeBeforeVerification = $provider->outcomeFor($student, $f['subject'], $f['year']);
    expect($outcomeBeforeVerification?->isVerified)->toBeFalse();

    app(ModerateProjectAction::class)->execute(new ModerateProjectData(
        learnerProjectId: $learnerProject->id, moderatorStaffId: $f['staff']->id,
        moderatedMark: 85.0, moderationNote: 'Adjusted for execution depth.',
    ));

    app(VerifyProjectAction::class)->execute(new VerifyProjectData(
        learnerProjectId: $learnerProject->id, verifiedByUserId: $f['user']->id,
    ));

    $learnerProject->refresh();
    $outcome = $provider->outcomeFor($student, $f['subject'], $f['year']);

    expect($learnerProject->status)->toBe('verified')
        ->and($learnerProject->raw_mark)->toEqual('80.00')
        ->and((float) $learnerProject->moderated_mark)->toBe(85.0)
        ->and((float) $learnerProject->percent)->toBe(85.0)
        ->and($outcome)->not->toBeNull()
        ->and($outcome->isVerified)->toBeTrue()
        ->and($outcome->percent)->toBe(85.0)
        ->and($outcome->instrumentCode)->toBe('SBP');
});

it('refuses to update or delete a legacy CALA record', function (): void {
    $f = aca06Fixture();
    $student = aca06Student($f, 'CalaLearner');

    $record = LegacyCalaRecord::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id,
        'student_id' => $student->id, 'subject_id' => $f['subject']->id,
    ]);

    expect(fn () => $record->update(['raw_mark' => '99.00']))->toThrow(RuntimeException::class)
        ->and(fn () => $record->delete())->toThrow(RuntimeException::class);
});
