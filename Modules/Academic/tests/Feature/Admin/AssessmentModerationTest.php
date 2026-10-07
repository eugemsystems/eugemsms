<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\CreateAssessmentAction;
use Modules\Academic\Domain\Actions\CreateAssessmentTypeAction;
use Modules\Academic\Domain\Actions\EnterMarkAction;
use Modules\Academic\Domain\Actions\SubmitAssessmentMarksAction;
use Modules\Academic\Domain\DataObjects\CreateAssessmentData;
use Modules\Academic\Domain\DataObjects\CreateAssessmentTypeData;
use Modules\Academic\Domain\DataObjects\EnterMarkData;
use Modules\Academic\Domain\DataObjects\SubmitAssessmentMarksData;
use Modules\Academic\Livewire\Marks\Moderate;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book D ACA-05 §6 — `Marks\Moderate` (the one gap `.ai/rules/academic.md`
 * still lists under ACA-05: distribution/outlier stats + optional
 * `submitted -> moderated` sign-off). Own, distinctly-named fixture —
 * see `AssessmentAdminUiTest`'s own note on why a Pest helper in one
 * file can't be relied on from another run standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, framework: CurriculumFramework, section: SchoolSection, gradeLevel: GradeLevel, subject: Subject, user: User}
 */
function moderationFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $framework = CurriculumFramework::factory()->for($school)->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'framework' => $framework,
        'section' => $section, 'gradeLevel' => $gradeLevel, 'subject' => $subject, 'user' => User::factory()->create(),
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function moderationAssessment(array $f): Assessment
{
    $type = app(CreateAssessmentTypeAction::class)->execute(new CreateAssessmentTypeData(
        schoolId: $f['school']->id, code: 'TOPIC_TEST', name: 'Topic test', category: 'coursework', defaultWeightPercent: 100,
    ));

    return app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        assessmentTypeId: $type->id,
        subjectId: $f['subject']->id,
        title: 'Term 1 Topic Test',
        maxMark: 100,
        weightPercent: 100,
        createdByUserId: $f['user']->id,
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function moderationStudent(array $f): Student
{
    $student = Student::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id, 'status' => 'active']);

    LearnerSubjectEnrolment::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id,
        'term_id' => $f['term']->id,
        'student_id' => $student->id,
        'subject_id' => $f['subject']->id,
        'status' => 'active',
        'effective_from' => now()->subDays(10)->toDateString(),
        'effective_to' => null,
        'added_by' => $f['user']->id,
    ]);

    return $student;
}

/**
 * @param  array<string, mixed>  $f
 */
function moderationUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        [$moduleCode, $resource, $action] = explode('.', $permissionName);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($moduleCode), 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    if ($grants !== []) {
        app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
            userId: $user->id, schoolId: $f['school']->id, grants: $grants,
        ));
    }

    return $user;
}

it('serves Marks\\Moderate through a real routed request', function (): void {
    $f = moderationFixture();
    $assessment = moderationAssessment($f);
    $user = moderationUser($f, 'academic.result.moderate');

    $this->actingAs($user)
        ->get(route('academic.marks.moderate', [$f['school'], $assessment]))
        ->assertOk();
});

it('refuses Marks\\Moderate to a user without academic.result.moderate', function (): void {
    $f = moderationFixture();
    $assessment = moderationAssessment($f);
    $user = moderationUser($f);

    Livewire::actingAs($user)->test(Moderate::class, ['school' => $f['school'], 'assessment' => $assessment])
        ->assertForbidden();
});

it('refuses to moderate an assessment that has not been submitted', function (): void {
    $f = moderationFixture();
    $assessment = moderationAssessment($f);
    $user = moderationUser($f, 'academic.result.moderate');

    Livewire::actingAs($user)->test(Moderate::class, ['school' => $f['school'], 'assessment' => $assessment])
        ->call('moderate');

    expect($assessment->fresh()->status)->toBe('draft');
});

it('moderates a submitted assessment, recording who, when, and the distribution summary', function (): void {
    $f = moderationFixture();
    $assessment = moderationAssessment($f);
    $enterer = moderationUser($f, 'academic.result.enter');
    $moderator = moderationUser($f, 'academic.result.moderate');

    $low = moderationStudent($f);
    $high = moderationStudent($f);

    app(EnterMarkAction::class)->execute(new EnterMarkData(assessmentId: $assessment->id, studentId: $low->id, enteredByUserId: $enterer->id, rawMark: 40.0));
    app(EnterMarkAction::class)->execute(new EnterMarkData(assessmentId: $assessment->id, studentId: $high->id, enteredByUserId: $enterer->id, rawMark: 60.0));

    app(SubmitAssessmentMarksAction::class)->execute(new SubmitAssessmentMarksData(assessmentId: $assessment->id, submittedByUserId: $enterer->id));

    $component = Livewire::actingAs($moderator)->test(Moderate::class, ['school' => $f['school'], 'assessment' => $assessment->fresh()])
        ->assertSee('50.0%')
        ->set('moderationNote', 'Reviewed against the original scripts.')
        ->call('moderate');

    $assessment = $assessment->fresh();

    expect($assessment->status)->toBe('moderated')
        ->and($assessment->moderated_by)->toBe($moderator->id)
        ->and($assessment->moderated_at)->not->toBeNull()
        ->and($assessment->moderation_note)->toBe('Reviewed against the original scripts.');

    $component->assertOk();
});
