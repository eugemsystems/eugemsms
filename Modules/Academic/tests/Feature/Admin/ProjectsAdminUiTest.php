<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\CreateAssessmentInstrumentAction;
use Modules\Academic\Domain\Actions\CreateProjectRubricAction;
use Modules\Academic\Domain\DataObjects\CreateAssessmentInstrumentData;
use Modules\Academic\Domain\DataObjects\CreateProjectRubricData;
use Modules\Academic\Domain\DataObjects\RubricCriterionInput;
use Modules\Academic\Livewire\Projects\Approve;
use Modules\Academic\Livewire\Projects\Briefs;
use Modules\Academic\Livewire\Projects\Mark;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectRubric;
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
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book E ACA-06 admin UI — School-Based Projects & Legacy CALA. Own,
 * distinctly-named fixture — see `StaffAdminUiTest`'s own note on why a
 * Pest helper defined in one test file can't be relied on from another
 * run standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, gradeLevel: GradeLevel, subject: Subject, instrument: AssessmentInstrument, rubric: ProjectRubric, user: User}
 */
function projectsAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $framework = CurriculumFramework::factory()->for($school)->create();
    $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id, 'requires_sbp' => true]);

    $instrument = app(CreateAssessmentInstrumentAction::class)->execute(new CreateAssessmentInstrumentData(
        schoolId: $school->id, frameworkId: $framework->id, code: 'SBP', name: 'School-Based Project',
    ));

    $rubric = app(CreateProjectRubricAction::class)->execute(new CreateProjectRubricData(
        schoolId: $school->id,
        name: 'Generic SBP Rubric',
        criteria: [
            new RubricCriterionInput(criterion: 'Research', maxMark: 50, weightPercent: 50, performanceLevels: []),
            new RubricCriterionInput(criterion: 'Presentation', maxMark: 50, weightPercent: 50, performanceLevels: []),
        ],
    ));

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'gradeLevel' => $gradeLevel,
        'subject' => $subject, 'instrument' => $instrument, 'rubric' => $rubric, 'user' => User::factory()->create(),
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function projectsAdminUser(array $f, string ...$permissionNames): User
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

/**
 * @param  array<string, mixed>  $f
 */
function projectsAdminEnrolledStudent(array $f): Student
{
    $student = Student::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id, 'status' => 'enrolled']);

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
function projectsAdminBrief(array $f): ProjectBrief
{
    $component = Livewire::actingAs(projectsAdminUser($f, 'academic.projects.view', 'academic.projects.manage'))
        ->test(Briefs::class, ['school' => $f['school']])
        ->set('instrumentId', $f['instrument']->id)
        ->set('subjectId', $f['subject']->id)
        ->set('gradeLevelId', $f['gradeLevel']->id)
        ->set('rubricId', $f['rubric']->id)
        ->set('title', 'Local Water Access Project')
        ->set('description', 'Investigate a local heritage-linked problem.')
        ->set('startsOn', now()->toDateString())
        ->set('dueOn', now()->addWeeks(6)->toDateString())
        ->call('create');

    return ProjectBrief::where('school_id', $f['school']->id)->latest('id')->first();
}

it('serves every Projects screen with no route parameter through a real routed request', function (): void {
    $f = projectsAdminFixture();
    $user = projectsAdminUser(
        $f,
        'academic.curriculum.view', 'academic.projects.view', 'academic.projects.approve',
        'academic.projects.mark', 'academic.projects.moderate', 'academic.projects.verify',
    );

    $this->actingAs($user)->get(route('academic.projects.instruments', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.projects.briefs', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.projects.rubrics', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.projects.approve', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.projects.tracker', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.projects.mark', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.projects.moderate', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.projects.verify', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.projects.cala-archive', $f['school']))->assertOk();
});

it('refuses Mark to a user without academic.projects.mark', function (): void {
    $f = projectsAdminFixture();
    $user = projectsAdminUser($f);

    Livewire::actingAs($user)->test(Mark::class, ['school' => $f['school']])->assertForbidden();
});

it('issues a brief and creates a learner_project for every currently-enrolled learner (AC-ACA-06-001)', function (): void {
    $f = projectsAdminFixture();
    $student = projectsAdminEnrolledStudent($f);
    $brief = projectsAdminBrief($f);
    $hod = projectsAdminUser($f, 'academic.projects.approve');
    // ApproveProjectBriefData::$approvedByStaffId is a staff.id, resolved
    // from the acting user's own linked staff record — see
    // `.ai/rules/academic.md`'s note on this exact id-space pitfall.
    Staff::factory()->create(['school_id' => $f['school']->id, 'user_id' => $hod->id]);

    Livewire::actingAs($hod)->test(Approve::class, ['school' => $f['school']])
        ->call('approve', $brief->id)
        ->call('issue', $brief->id);

    expect(ProjectBrief::find($brief->id)->status)->toBe('issued')
        ->and(LearnerProject::where('brief_id', $brief->id)->where('student_id', $student->id)->exists())->toBeTrue();
});

it('refuses a second brief for the same subject, level, and year unless the project limit is overridden (AC-ACA-06-002)', function (): void {
    $f = projectsAdminFixture();
    projectsAdminBrief($f);

    expect(ProjectBrief::where('school_id', $f['school']->id)->count())->toBe(1);

    $user = projectsAdminUser($f, 'academic.projects.view', 'academic.projects.manage');

    Livewire::actingAs($user)->test(Briefs::class, ['school' => $f['school']])
        ->set('instrumentId', $f['instrument']->id)
        ->set('subjectId', $f['subject']->id)
        ->set('gradeLevelId', $f['gradeLevel']->id)
        ->set('rubricId', $f['rubric']->id)
        ->set('title', 'Second Brief Same Subject')
        ->set('description', 'A second project for the same subject and level this year.')
        ->set('startsOn', now()->toDateString())
        ->set('dueOn', now()->addWeeks(6)->toDateString())
        ->call('create');

    // Refused — still only the one brief from projectsAdminBrief() exists.
    expect(ProjectBrief::where('school_id', $f['school']->id)->count())->toBe(1);
});
