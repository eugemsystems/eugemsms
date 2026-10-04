<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\CreateAssessmentAction;
use Modules\Academic\Domain\Actions\CreateAssessmentTypeAction;
use Modules\Academic\Domain\DataObjects\CreateAssessmentData;
use Modules\Academic\Domain\DataObjects\CreateAssessmentTypeData;
use Modules\Academic\Livewire\Grading\Scales;
use Modules\Academic\Livewire\Marks\Entry;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\GradingScale;
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
 * Book D ACA-05 §6 admin UI — Assessment, Grading & Report Cards. Own,
 * distinctly-named fixture — see `StaffAdminUiTest`'s own note on why
 * a Pest helper defined in one test file can't be relied on from
 * another run standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, framework: CurriculumFramework, section: SchoolSection, gradeLevel: GradeLevel, subject: Subject, user: User}
 */
function assessmentAdminFixture(): array
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
function assessmentAdminAssessment(array $f): Assessment
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
function assessmentAdminStudent(array $f): Student
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
function assessmentAdminUser(array $f, string ...$permissionNames): User
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

it('serves Marks\\Entry through a real routed request', function (): void {
    $f = assessmentAdminFixture();
    $assessment = assessmentAdminAssessment($f);
    $user = assessmentAdminUser($f, 'academic.result.enter');

    $this->actingAs($user)
        ->get(route('academic.marks.entry', [$f['school'], $assessment]))
        ->assertOk();
});

it('serves every other Assessment screen through a real routed request', function (): void {
    $f = assessmentAdminFixture();
    $assessment = assessmentAdminAssessment($f);
    $user = assessmentAdminUser($f, 'academic.grading.view', 'academic.assessment.view', 'academic.result.compute', 'academic.result.view', 'academic.result.amend');

    $this->actingAs($user)->get(route('academic.grading.scales', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.assessment.types', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.assessment.planner', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.results.compute', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.results.comments', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.marks.amend', [$f['school'], $assessment]))->assertOk();
});

it('refuses Marks\\Entry to a user without academic.result.enter', function (): void {
    $f = assessmentAdminFixture();
    $assessment = assessmentAdminAssessment($f);
    $user = assessmentAdminUser($f);

    Livewire::actingAs($user)->test(Entry::class, ['school' => $f['school'], 'assessment' => $assessment])
        ->assertForbidden();
});

it('enters a mark, excludes an absent learner from the mean rather than scoring zero (BR-ACA-05-008)', function (): void {
    $f = assessmentAdminFixture();
    $assessment = assessmentAdminAssessment($f);
    $present = assessmentAdminStudent($f);
    $absent = assessmentAdminStudent($f);
    $user = assessmentAdminUser($f, 'academic.result.enter');

    Livewire::actingAs($user)->test(Entry::class, ['school' => $f['school'], 'assessment' => $assessment])
        ->set("marks.{$present->id}", '72')
        ->set("absentFlags.{$absent->id}", true)
        ->call('saveAll');

    $presentMark = AssessmentMark::where('assessment_id', $assessment->id)->where('student_id', $present->id)->first();
    $absentMark = AssessmentMark::where('assessment_id', $assessment->id)->where('student_id', $absent->id)->first();

    expect($presentMark->raw_mark)->toEqual(72.0)
        ->and($presentMark->is_absent)->toBeFalse()
        ->and($absentMark->is_absent)->toBeTrue()
        ->and($absentMark->raw_mark)->toBeNull();
});

it('refuses a grading scale whose bands leave a gap (AC-ACA-05-008)', function (): void {
    $f = assessmentAdminFixture();
    $user = assessmentAdminUser($f, 'academic.grading.view', 'academic.grading.manage');

    $component = Livewire::actingAs($user)->test(Scales::class, ['school' => $f['school']])
        ->set('code', 'GAPPY')
        ->set('name', 'Gappy scale')
        ->set('bands', [
            ['grade' => 'A', 'minPercent' => '75', 'maxPercent' => '100', 'points' => '', 'isPass' => true],
            ['grade' => 'U', 'minPercent' => '0', 'maxPercent' => '49', 'points' => '', 'isPass' => false],
        ])
        ->call('create');

    expect(GradingScale::where('school_id', $f['school']->id)->where('code', 'GAPPY')->exists())->toBeFalse();
});
