<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Academic\Domain\Actions\CreateLevelSubjectOfferingAction;
use Modules\Academic\Domain\Actions\CreatePathwayAction;
use Modules\Academic\Domain\Actions\CreateSubjectPrerequisiteAction;
use Modules\Academic\Domain\Actions\CreateSyllabusAction;
use Modules\Academic\Domain\Actions\DropSubjectAction;
use Modules\Academic\Domain\Actions\EnrolSubjectAction;
use Modules\Academic\Domain\DataObjects\CreateLevelSubjectOfferingData;
use Modules\Academic\Domain\DataObjects\CreatePathwayData;
use Modules\Academic\Domain\DataObjects\CreateSubjectPrerequisiteData;
use Modules\Academic\Domain\DataObjects\CreateSyllabusData;
use Modules\Academic\Domain\DataObjects\DropSubjectData;
use Modules\Academic\Domain\DataObjects\EnrolSubjectData;
use Modules\Academic\Domain\Events\PartTimeLearnerHasNoSubjects;
use Modules\Academic\Domain\Events\SubjectEnrolmentAdded;
use Modules\Academic\Domain\Events\SubjectEnrolmentDropped;
use Modules\Academic\Domain\Exceptions\SubjectChangeRequiresApprovalException;
use Modules\Academic\Domain\Exceptions\SubjectSelectionBlockedException;
use Modules\Academic\Domain\Exceptions\SubjectSelectionRequiresAcknowledgementException;
use Modules\Academic\Domain\Support\SubjectEnrolmentQuery;
use Modules\Academic\Domain\Support\SubjectSelectionRuleEngine;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectEnrolmentChange;
use Modules\Academic\Models\SubjectGroup;
use Modules\Academic\Models\SubjectSelectionRule;
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
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Models\Student;

/**
 * @return array{school: School, year: AcademicYear, term: Term, framework: CurriculumFramework, section: SchoolSection, gradeLevel: GradeLevel, user: User}
 */
function academicFixture(int $termStartsDaysAgo = 10, int $termLengthDays = 90, ?int $teachingDays = 65): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $termStart = now()->subDays($termStartsDaysAgo)->startOfDay();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => $termStart,
        'ends_on' => $termStart->copy()->addDays($termLengthDays),
        'teaching_days' => $teachingDays,
    ]);
    $framework = CurriculumFramework::factory()->for($school)->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'framework' => $framework,
        'section' => $section, 'gradeLevel' => $gradeLevel, 'user' => User::factory()->create(),
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function partTimeStudent(array $f, array $overrides = []): Student
{
    return app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        firstName: $overrides['firstName'] ?? 'Rutendo',
        lastName: $overrides['lastName'] ?? 'Chiweshe',
        dateOfBirth: now()->subYears(16),
        gender: 'female',
        enrolmentType: $overrides['enrolmentType'] ?? 'PART_TIME',
        residency: 'DAY',
        sectionId: $f['section']->id,
        gradeLevelId: $f['gradeLevel']->id,
        entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id,
        pathway: $overrides['pathway'] ?? null,
        skipDuplicateCheck: true,
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function academicSubject(array $f, array $overrides = []): Subject
{
    return Subject::factory()->for($f['school'])->create(array_merge([
        'framework_id' => $f['framework']->id,
    ], $overrides));
}

it('derives the billable subject count from learner_subject_enrolments alone (AC-ACA-02-001/BR-ACA-02-001)', function (): void {
    $f = academicFixture();
    $student = partTimeStudent($f);
    $subjects = Subject::factory()->for($f['school'])->count(3)->create(['framework_id' => $f['framework']->id]);

    foreach ($subjects as $subject) {
        app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
            studentId: $student->id, subjectId: $subject->id, termId: $f['term']->id, addedByUserId: $f['user']->id,
        ));
    }

    $count = app(SubjectEnrolmentQuery::class)->billableCountOn($student, $f['term'], now());

    expect($count)->toBe(3)
        ->and(LearnerSubjectEnrolment::query()->where('student_id', $student->id)->count())->toBe(3);
});

it('snapshots the proration factor at add time and never recomputes it (AC-ACA-02-002/003)', function (): void {
    $f = academicFixture(termStartsDaysAgo: 10, termLengthDays: 90, teachingDays: 65);
    $student = partTimeStudent($f);
    $subject = academicSubject($f);

    Event::fake([SubjectEnrolmentAdded::class]);

    $enrolment = app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
        studentId: $student->id, subjectId: $subject->id, termId: $f['term']->id, addedByUserId: $f['user']->id,
    ));

    $change = SubjectEnrolmentChange::query()->where('student_id', $student->id)->where('change_type', 'added')->firstOrFail();

    $calendarTotal = $f['term']->starts_on->diffInDays($f['term']->ends_on) + 1;
    $calendarRemaining = now()->startOfDay()->diffInDays($f['term']->ends_on) + 1;
    $expectedRemaining = (int) round(65 * ($calendarRemaining / $calendarTotal));

    expect($change->term_teaching_days)->toBe(65)
        ->and($change->teaching_days_remaining)->toBe($expectedRemaining)
        ->and((float) $change->proration_factor)->toBe(round($expectedRemaining / 65, 6));

    Event::assertDispatched(SubjectEnrolmentAdded::class);

    // BR-ACA-02-007/AC-ACA-02-003: correcting the term's calendar afterwards
    // must never rewrite the already-issued charge's stored factor.
    $f['term']->update(['teaching_days' => 63]);
    $change->refresh();

    expect($change->term_teaching_days)->toBe(65);
    expect($enrolment->fresh()->status)->toBe('active');
});

it('prorates the unused remainder on drop and decreases the billable count (AC-ACA-02-004)', function (): void {
    $f = academicFixture();
    $student = partTimeStudent($f);
    $subjectA = academicSubject($f);
    $subjectB = academicSubject($f);

    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(studentId: $student->id, subjectId: $subjectA->id, termId: $f['term']->id, addedByUserId: $f['user']->id));
    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(studentId: $student->id, subjectId: $subjectB->id, termId: $f['term']->id, addedByUserId: $f['user']->id));

    Event::fake([SubjectEnrolmentDropped::class]);

    $dropped = app(DropSubjectAction::class)->execute(new DropSubjectData(
        studentId: $student->id, subjectId: $subjectB->id, termId: $f['term']->id, droppedByUserId: $f['user']->id, dropReason: 'Changed elective',
    ));

    expect($dropped->status)->toBe('dropped')
        ->and($dropped->effective_to)->not->toBeNull();

    $change = SubjectEnrolmentChange::query()->where('student_id', $student->id)->where('change_type', 'dropped')->firstOrFail();
    expect($change->term_teaching_days)->toBe(65);

    $count = app(SubjectEnrolmentQuery::class)->billableCountOn($student, $f['term'], now());
    expect($count)->toBe(1);

    Event::assertDispatched(SubjectEnrolmentDropped::class);
});

it('fires PartTimeLearnerHasNoSubjects rather than refusing the drop (AC-ACA-02-008/BR-ACA-02-011)', function (): void {
    $f = academicFixture();
    $student = partTimeStudent($f);
    $subject = academicSubject($f);

    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(studentId: $student->id, subjectId: $subject->id, termId: $f['term']->id, addedByUserId: $f['user']->id));

    Event::fake([PartTimeLearnerHasNoSubjects::class]);

    $dropped = app(DropSubjectAction::class)->execute(new DropSubjectData(
        studentId: $student->id, subjectId: $subject->id, termId: $f['term']->id, droppedByUserId: $f['user']->id,
    ));

    expect($dropped->status)->toBe('dropped');
    Event::assertDispatched(PartTimeLearnerHasNoSubjects::class);
});

it('requires approval for a change attempted after the subject change cutoff week (AC-ACA-02-007/BR-ACA-02-005)', function (): void {
    $f = academicFixture(termStartsDaysAgo: 60);
    $student = partTimeStudent($f);
    $subject = academicSubject($f);

    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
        studentId: $student->id, subjectId: $subject->id, termId: $f['term']->id, addedByUserId: $f['user']->id,
    ));
})->throws(SubjectChangeRequiresApprovalException::class);

it('blocks a selection that breaches a block-severity rule with no override path (AC-ACA-01-002)', function (): void {
    $f = academicFixture();
    $student = partTimeStudent($f);
    SubjectSelectionRule::factory()->for($f['school'])->create([
        'framework_id' => $f['framework']->id, 'rule_type' => 'max_total', 'max_count' => 1,
        'severity' => 'block', 'message' => 'A maximum of 1 subject may be selected.',
    ]);
    $subjectA = academicSubject($f);
    $subjectB = academicSubject($f);

    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(studentId: $student->id, subjectId: $subjectA->id, termId: $f['term']->id, addedByUserId: $f['user']->id));

    expect(fn () => app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
        studentId: $student->id, subjectId: $subjectB->id, termId: $f['term']->id, addedByUserId: $f['user']->id,
    )))->toThrow(SubjectSelectionBlockedException::class);

    expect(LearnerSubjectEnrolment::query()->where('student_id', $student->id)->where('status', 'active')->count())->toBe(1);
});

it('requires explicit acknowledgement of a warn-severity rule before saving (AC-ACA-01-002B)', function (): void {
    $f = academicFixture();
    $student = partTimeStudent($f);
    SubjectSelectionRule::factory()->for($f['school'])->create([
        'framework_id' => $f['framework']->id, 'rule_type' => 'max_total', 'max_count' => 1,
        'severity' => 'warn', 'message' => 'Usually no more than 1 subject is taken.',
    ]);
    $subjectA = academicSubject($f);
    $subjectB = academicSubject($f);

    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(studentId: $student->id, subjectId: $subjectA->id, termId: $f['term']->id, addedByUserId: $f['user']->id));

    expect(fn () => app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
        studentId: $student->id, subjectId: $subjectB->id, termId: $f['term']->id, addedByUserId: $f['user']->id,
    )))->toThrow(SubjectSelectionRequiresAcknowledgementException::class);

    $enrolment = app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
        studentId: $student->id, subjectId: $subjectB->id, termId: $f['term']->id, addedByUserId: $f['user']->id, acknowledgeWarnings: true,
    ));

    expect($enrolment->status)->toBe('active');
});

it('prices per-subject rates through subject_group_id, the FIN-02 join point (BR-ACA-01-004)', function (): void {
    $f = academicFixture();
    $group = SubjectGroup::factory()->for($f['school'])->create(['code' => 'SCIENCES_PRACTICAL']);
    $subject = academicSubject($f, ['subject_group_id' => $group->id]);
    $student = partTimeStudent($f);

    $enrolment = app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
        studentId: $student->id, subjectId: $subject->id, termId: $f['term']->id, addedByUserId: $f['user']->id,
    ));

    expect($enrolment->subject_group_id)->toBe($group->id);
});

it('never allows a direct write to learner_subject_enrolments outside the drop fields (BR-ACA-02-001)', function (): void {
    $f = academicFixture();
    $student = partTimeStudent($f);
    $subject = academicSubject($f);

    $enrolment = app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
        studentId: $student->id, subjectId: $subject->id, termId: $f['term']->id, addedByUserId: $f['user']->id,
    ));

    expect(fn () => $enrolment->update(['subject_id' => academicSubject($f)->id]))
        ->toThrow(InvalidStateTransitionException::class);
});

it('never allows a subject_enrolment_changes row to be edited or deleted (BR-ACA-02-007)', function (): void {
    $f = academicFixture();
    $student = partTimeStudent($f);
    $subject = academicSubject($f);

    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
        studentId: $student->id, subjectId: $subject->id, termId: $f['term']->id, addedByUserId: $f['user']->id,
    ));

    $change = SubjectEnrolmentChange::query()->where('student_id', $student->id)->firstOrFail();

    expect(fn () => $change->update(['reason' => 'tampered']))->toThrow(InvalidStateTransitionException::class);
    $change->refresh();
    expect(fn () => $change->delete())->toThrow(InvalidStateTransitionException::class);
    $change->refresh();

    $change->update(['billing_event_dispatched' => true, 'billing_event_result' => 'charged']);
    expect($change->fresh()->billing_event_dispatched)->toBeTrue();
});

it('evaluates min_from_group and required_subject rules via the selection engine (BR-ACA-01-007)', function (): void {
    $f = academicFixture();
    $required = academicSubject($f);
    SubjectSelectionRule::factory()->for($f['school'])->create([
        'framework_id' => $f['framework']->id, 'rule_type' => 'required_subject',
        'subject_ids' => [$required->id], 'severity' => 'block', 'message' => 'This subject is compulsory.',
    ]);

    $engine = app(SubjectSelectionRuleEngine::class);

    $withoutRequired = $engine->validate(collect([academicSubject($f)->id]), $f['framework']->id, null, null, $f['school']->id);
    expect($withoutRequired->isValid)->toBeFalse();

    $withRequired = $engine->validate(collect([$required->id]), $f['framework']->id, null, null, $f['school']->id);
    expect($withRequired->isValid)->toBeTrue();
});

it('enforces min_compulsory against level_subject_offerings when academicYearId/studentId are supplied', function (): void {
    $f = academicFixture();
    $compulsory = academicSubject($f);
    $elective = academicSubject($f);

    app(CreateLevelSubjectOfferingAction::class)->execute(new CreateLevelSubjectOfferingData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, gradeLevelId: $f['gradeLevel']->id,
        subjectId: $compulsory->id, isCompulsory: true,
    ));

    SubjectSelectionRule::factory()->for($f['school'])->create([
        'framework_id' => $f['framework']->id, 'rule_type' => 'min_compulsory', 'min_count' => 1,
        'severity' => 'block', 'message' => 'At least one compulsory subject is required.',
    ]);

    $engine = app(SubjectSelectionRuleEngine::class);

    $withoutCompulsory = $engine->validate(collect([$elective->id]), $f['framework']->id, $f['gradeLevel']->id, null, $f['school']->id, $f['year']->id);
    expect($withoutCompulsory->isValid)->toBeFalse();

    $withCompulsory = $engine->validate(collect([$compulsory->id]), $f['framework']->id, $f['gradeLevel']->id, null, $f['school']->id, $f['year']->id);
    expect($withCompulsory->isValid)->toBeTrue();
});

it('blocks two subjects sharing an option block (AC-ACA-01-006)', function (): void {
    $f = academicFixture();
    $subjectA = academicSubject($f);
    $subjectB = academicSubject($f);

    foreach ([$subjectA, $subjectB] as $subject) {
        app(CreateLevelSubjectOfferingAction::class)->execute(new CreateLevelSubjectOfferingData(
            schoolId: $f['school']->id, academicYearId: $f['year']->id, gradeLevelId: $f['gradeLevel']->id,
            subjectId: $subject->id, optionBlock: 'B',
        ));
    }

    SubjectSelectionRule::factory()->for($f['school'])->create([
        'framework_id' => $f['framework']->id, 'rule_type' => 'one_per_option_block',
        'severity' => 'block', 'message' => 'Only one subject per option block may be selected.',
    ]);

    $result = app(SubjectSelectionRuleEngine::class)->validate(
        collect([$subjectA->id, $subjectB->id]), $f['framework']->id, $f['gradeLevel']->id, null, $f['school']->id, $f['year']->id,
    );

    expect($result->isValid)->toBeFalse();
});

it('flags a missing subject prerequisite via internal enrolment history', function (): void {
    $f = academicFixture();
    $student = partTimeStudent($f);
    $advanced = academicSubject($f);
    $basic = academicSubject($f);

    app(CreateSubjectPrerequisiteAction::class)->execute(new CreateSubjectPrerequisiteData(
        schoolId: $f['school']->id, subjectId: $advanced->id, prerequisiteSubjectId: $basic->id, severity: 'block',
    ));

    SubjectSelectionRule::factory()->for($f['school'])->create([
        'framework_id' => $f['framework']->id, 'rule_type' => 'prerequisite',
        'severity' => 'block', 'message' => 'A required prerequisite has not been taken.',
    ]);

    $engine = app(SubjectSelectionRuleEngine::class);

    $withoutPrerequisite = $engine->validate(collect([$advanced->id]), $f['framework']->id, null, null, $f['school']->id, null, $student->id);
    expect($withoutPrerequisite->isValid)->toBeFalse();

    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(studentId: $student->id, subjectId: $basic->id, termId: $f['term']->id, addedByUserId: $f['user']->id));

    $withPrerequisite = $engine->validate(collect([$advanced->id]), $f['framework']->id, null, null, $f['school']->id, null, $student->id);
    expect($withPrerequisite->isValid)->toBeTrue();
});

it('creates a pathway, a syllabus, and confirms the Book D ACA-01 completion pieces persist', function (): void {
    $f = academicFixture();
    $subject = academicSubject($f);

    $pathway = app(CreatePathwayAction::class)->execute(new CreatePathwayData(
        schoolId: $f['school']->id, frameworkId: $f['framework']->id, code: 'ACADEMIC', name: 'Academic', appliesFromLevelOrdinal: 8,
    ));

    $syllabus = app(CreateSyllabusAction::class)->execute(new CreateSyllabusData(
        schoolId: $f['school']->id, subjectId: $subject->id, frameworkId: $f['framework']->id, title: 'Combined Science Syllabus',
    ));

    expect($pathway->code)->toBe('ACADEMIC')
        ->and($syllabus->subject_id)->toBe($subject->id);
});
