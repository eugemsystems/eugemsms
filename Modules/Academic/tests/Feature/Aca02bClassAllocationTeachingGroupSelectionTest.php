<?php

use App\Models\User;
use Modules\Academic\Domain\Actions\AllocateClassAction;
use Modules\Academic\Domain\Actions\AssignToTeachingGroupAction;
use Modules\Academic\Domain\Actions\CreateLevelSubjectOfferingAction;
use Modules\Academic\Domain\Actions\CreateTeachingGroupAction;
use Modules\Academic\Domain\Actions\GuardianApproveSubjectSelectionAction;
use Modules\Academic\Domain\Actions\RejectSubjectSelectionAction;
use Modules\Academic\Domain\Actions\SchoolApproveSubjectSelectionAction;
use Modules\Academic\Domain\Actions\SubmitSubjectSelectionAction;
use Modules\Academic\Domain\DataObjects\AllocateClassData;
use Modules\Academic\Domain\DataObjects\AssignToTeachingGroupData;
use Modules\Academic\Domain\DataObjects\CreateLevelSubjectOfferingData;
use Modules\Academic\Domain\DataObjects\CreateTeachingGroupData;
use Modules\Academic\Domain\DataObjects\GuardianApproveSubjectSelectionData;
use Modules\Academic\Domain\DataObjects\RejectSubjectSelectionData;
use Modules\Academic\Domain\DataObjects\SchoolApproveSubjectSelectionData;
use Modules\Academic\Domain\DataObjects\SubmitSubjectSelectionData;
use Modules\Academic\Domain\Exceptions\TeachingGroupCapacityExceededException;
use Modules\Academic\Domain\Exceptions\TeachingGroupCapacityRequiresAcknowledgementException;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
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
 * @return array{school: School, year: AcademicYear, term: Term, framework: CurriculumFramework, section: SchoolSection, gradeLevel: GradeLevel, user: User}
 */
function aca02bFixture(): array
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
function aca02bStudent(array $f): Student
{
    return app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        firstName: 'Tadiwa',
        lastName: 'Moyo',
        dateOfBirth: now()->subYears(15),
        gender: 'male',
        enrolmentType: 'FULL_TIME',
        residency: 'DAY',
        sectionId: $f['section']->id,
        gradeLevelId: $f['gradeLevel']->id,
        entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id,
        skipDuplicateCheck: true,
    ));
}

it('allocates a class and auto-enrols compulsory subjects on first allocation (BR-ACA-02-004/014)', function (): void {
    $f = aca02bFixture();
    $student = aca02bStudent($f);
    $class = SchoolClass::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id]);

    $compulsory = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    app(CreateLevelSubjectOfferingAction::class)->execute(new CreateLevelSubjectOfferingData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, gradeLevelId: $f['gradeLevel']->id,
        subjectId: $compulsory->id, isCompulsory: true,
    ));

    $elective = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    app(CreateLevelSubjectOfferingAction::class)->execute(new CreateLevelSubjectOfferingData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, gradeLevelId: $f['gradeLevel']->id,
        subjectId: $elective->id, isCompulsory: false,
    ));

    $allocation = app(AllocateClassAction::class)->execute(new AllocateClassData(
        studentId: $student->id, classId: $class->id, termId: $f['term']->id,
        allocatedByUserId: $f['user']->id, effectiveFrom: now(),
    ));

    expect($allocation->status)->toBe('confirmed')
        ->and(LearnerSubjectEnrolment::query()->where('student_id', $student->id)->where('subject_id', $compulsory->id)->exists())->toBeTrue()
        ->and(LearnerSubjectEnrolment::query()->where('student_id', $student->id)->where('subject_id', $elective->id)->exists())->toBeFalse();
});

it('supersedes rather than overwrites a mid-term class move (BR-ACA-02-014)', function (): void {
    $f = aca02bFixture();
    $student = aca02bStudent($f);
    $classA = SchoolClass::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id]);
    $classB = SchoolClass::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id]);

    $first = app(AllocateClassAction::class)->execute(new AllocateClassData(
        studentId: $student->id, classId: $classA->id, termId: $f['term']->id,
        allocatedByUserId: $f['user']->id, effectiveFrom: now()->subDays(5),
    ));

    $second = app(AllocateClassAction::class)->execute(new AllocateClassData(
        studentId: $student->id, classId: $classB->id, termId: $f['term']->id,
        allocatedByUserId: $f['user']->id, effectiveFrom: now(),
    ));

    expect($first->fresh()->status)->toBe('superseded')
        ->and($first->fresh()->effective_to)->not->toBeNull()
        ->and($second->fresh()->status)->toBe('confirmed')
        ->and(ClassAllocation::query()->where('student_id', $student->id)->count())->toBe(2);

    expect(fn () => $first->update(['class_id' => $classB->id]))->toThrow(InvalidStateTransitionException::class);
});

it('refuses a teaching group assignment over capacity when enforcement is on (BR-ACA-02-012)', function (): void {
    $f = aca02bFixture();
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $group = app(CreateTeachingGroupAction::class)->execute(new CreateTeachingGroupData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        subjectId: $subject->id, gradeLevelId: $f['gradeLevel']->id, code: 'SET1', name: 'Set 1', capacity: 1,
    ));

    $first = aca02bStudent($f);
    app(AssignToTeachingGroupAction::class)->execute(new AssignToTeachingGroupData(studentId: $first->id, teachingGroupId: $group->id));

    $second = aca02bStudent($f);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData(
        key: 'academic.enforce_teaching_group_capacity', scopeType: SettingScope::School, scopeId: $f['school']->id, value: true, setByUserId: $f['user']->id,
    ));

    expect(fn () => app(AssignToTeachingGroupAction::class)->execute(new AssignToTeachingGroupData(studentId: $second->id, teachingGroupId: $group->id)))
        ->toThrow(TeachingGroupCapacityExceededException::class);

    expect($group->fresh()->current_count)->toBe(1);
});

it('requires acknowledgement to overfill a teaching group when capacity is only warned (BR-ACA-02-012)', function (): void {
    $f = aca02bFixture();
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $group = app(CreateTeachingGroupAction::class)->execute(new CreateTeachingGroupData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        subjectId: $subject->id, gradeLevelId: $f['gradeLevel']->id, code: 'SET2', name: 'Set 2', capacity: 1,
    ));

    $first = aca02bStudent($f);
    app(AssignToTeachingGroupAction::class)->execute(new AssignToTeachingGroupData(studentId: $first->id, teachingGroupId: $group->id));

    $second = aca02bStudent($f);

    expect(fn () => app(AssignToTeachingGroupAction::class)->execute(new AssignToTeachingGroupData(studentId: $second->id, teachingGroupId: $group->id)))
        ->toThrow(TeachingGroupCapacityRequiresAcknowledgementException::class);

    $member = app(AssignToTeachingGroupAction::class)->execute(new AssignToTeachingGroupData(
        studentId: $second->id, teachingGroupId: $group->id, acknowledgeCapacityWarning: true,
    ));

    expect($member->student_id)->toBe($second->id)
        ->and($group->fresh()->current_count)->toBe(2);
});

it('moves a learner between teaching groups for the same subject rather than double-counting (BR-ACA-02-013)', function (): void {
    $f = aca02bFixture();
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $groupA = app(CreateTeachingGroupAction::class)->execute(new CreateTeachingGroupData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        subjectId: $subject->id, gradeLevelId: $f['gradeLevel']->id, code: 'SET-A', name: 'Set A',
    ));
    $groupB = app(CreateTeachingGroupAction::class)->execute(new CreateTeachingGroupData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        subjectId: $subject->id, gradeLevelId: $f['gradeLevel']->id, code: 'SET-B', name: 'Set B',
    ));

    $student = aca02bStudent($f);
    app(AssignToTeachingGroupAction::class)->execute(new AssignToTeachingGroupData(studentId: $student->id, teachingGroupId: $groupA->id));
    app(AssignToTeachingGroupAction::class)->execute(new AssignToTeachingGroupData(studentId: $student->id, teachingGroupId: $groupB->id));

    expect($groupA->fresh()->current_count)->toBe(0)
        ->and($groupB->fresh()->current_count)->toBe(1)
        ->and(TeachingGroupMember::query()->where('student_id', $student->id)->whereNull('effective_to')->count())->toBe(1);
});

it('takes a subject selection through submission, guardian and school approval into real enrolments (BR-ACA-02-015)', function (): void {
    $f = aca02bFixture();
    $student = aca02bStudent($f);
    $subjectA = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $subjectB = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);

    $submission = app(SubmitSubjectSelectionAction::class)->execute(new SubmitSubjectSelectionData(
        studentId: $student->id, academicYearId: $f['year']->id, gradeLevelId: $f['gradeLevel']->id,
        selectedSubjectIds: [$subjectA->id, $subjectB->id], submittedByUserId: $f['user']->id,
        indicativeFeeMinor: 15000, indicativeFeeCurrency: 'USD',
    ));

    expect($submission->status)->toBe('submitted');

    expect(fn () => app(SchoolApproveSubjectSelectionAction::class)->execute(new SchoolApproveSubjectSelectionData(
        submissionId: $submission->id, approvedByUserId: $f['user']->id,
    )))->toThrow(InvalidStateTransitionException::class);

    $guardianApproved = app(GuardianApproveSubjectSelectionAction::class)->execute(new GuardianApproveSubjectSelectionData(
        submissionId: $submission->id, approvedByUserId: $f['user']->id,
    ));
    expect($guardianApproved->status)->toBe('guardian_approved');

    $allocated = app(SchoolApproveSubjectSelectionAction::class)->execute(new SchoolApproveSubjectSelectionData(
        submissionId: $submission->id, approvedByUserId: $f['user']->id,
    ));

    expect($allocated->status)->toBe('allocated')
        ->and($allocated->allocated_at)->not->toBeNull()
        ->and(LearnerSubjectEnrolment::query()->where('student_id', $student->id)->where('subject_id', $subjectA->id)->exists())->toBeTrue()
        ->and(LearnerSubjectEnrolment::query()->where('student_id', $student->id)->where('subject_id', $subjectB->id)->exists())->toBeTrue();
});

it('rejects a subject selection submission with a reason and never allocates it', function (): void {
    $f = aca02bFixture();
    $student = aca02bStudent($f);
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);

    $submission = app(SubmitSubjectSelectionAction::class)->execute(new SubmitSubjectSelectionData(
        studentId: $student->id, academicYearId: $f['year']->id, gradeLevelId: $f['gradeLevel']->id,
        selectedSubjectIds: [$subject->id], submittedByUserId: $f['user']->id,
    ));

    $rejected = app(RejectSubjectSelectionAction::class)->execute(new RejectSubjectSelectionData(
        submissionId: $submission->id, rejectionReason: 'Subject not offered this year.',
    ));

    expect($rejected->status)->toBe('rejected')
        ->and($rejected->rejection_reason)->toBe('Subject not offered this year.')
        ->and(LearnerSubjectEnrolment::query()->where('student_id', $student->id)->exists())->toBeFalse();

    expect(fn () => app(SchoolApproveSubjectSelectionAction::class)->execute(new SchoolApproveSubjectSelectionData(
        submissionId: $submission->id, approvedByUserId: $f['user']->id,
    )))->toThrow(InvalidStateTransitionException::class);
});

it('never allows a direct edit to selected_subject_ids on a subject_selection_submissions row', function (): void {
    $f = aca02bFixture();
    $student = aca02bStudent($f);
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);

    $submission = app(SubmitSubjectSelectionAction::class)->execute(new SubmitSubjectSelectionData(
        studentId: $student->id, academicYearId: $f['year']->id, gradeLevelId: $f['gradeLevel']->id,
        selectedSubjectIds: [$subject->id], submittedByUserId: $f['user']->id,
    ));

    expect(fn () => $submission->update(['selected_subject_ids' => []]))
        ->toThrow(InvalidStateTransitionException::class);
});
