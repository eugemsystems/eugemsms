<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\CreateStaffAppraisalAction;
use Modules\People\Domain\Actions\SubmitAppraiserAssessmentAction;
use Modules\People\Domain\Actions\SubmitSelfAssessmentAction;
use Modules\People\Domain\DataObjects\CreateStaffAppraisalData;
use Modules\People\Domain\DataObjects\SubmitAppraiserAssessmentData;
use Modules\People\Domain\DataObjects\SubmitSelfAssessmentData;
use Modules\People\Livewire\Appraisal\Index;
use Modules\People\Livewire\Appraisal\Show;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffAppraisalRubric;

/**
 * The structured appraisal rubric (PPL-04 §5): mirrors `Academic\Supervision\Observe`'s
 * own rubric shape and validation, folded into `People\Appraisal\Index`/`Show` rather
 * than a separate screen -- same precedent ACA-11 already established.
 *
 * @return array{school: School, year: AcademicYear, term: Term, user: User}
 */
function appraisalRubricFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    SessionContext::set($year, $term);
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    return ['school' => $school, 'year' => $year, 'term' => $term, 'user' => $user];
}

/**
 * @param  array<string, mixed>  $f
 */
function appraisalRubricUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $name): PermissionGrantData {
        $parts = explode('.', $name);
        $permission = Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => $parts[1] ?? $parts[0], 'action' => end($parts)]);

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $f['school']->id, grants: $grants));

    return $user;
}

it('refuses rubric creation without people.staff.appraisal_rubric_manage, even with appraisal_manage held', function (): void {
    $f = appraisalRubricFixture();
    $this->actingAs(appraisalRubricUser($f, 'people.staff.appraisal_manage'));

    Livewire::test(Index::class, ['school' => $f['school']])
        ->set('rubricName', 'Annual Teaching Rubric')
        ->set('rubricCriteria', 'Job knowledge: unsatisfactory, developing, proficient, outstanding')
        ->call('createRubric')
        ->assertForbidden();
});

it('creates a rubric from newline-delimited criteria text and refuses one with fewer than two levels', function (): void {
    $f = appraisalRubricFixture();
    $this->actingAs(appraisalRubricUser($f, 'people.staff.appraisal_manage', 'people.staff.appraisal_rubric_manage'));

    Livewire::test(Index::class, ['school' => $f['school']])
        ->set('rubricName', 'Annual Teaching Rubric')
        ->set('rubricCriteria', "Job knowledge: unsatisfactory, developing, proficient, outstanding\nCollaboration: poor, good")
        ->call('createRubric')
        ->assertHasNoErrors();

    $rubric = StaffAppraisalRubric::sole();
    expect($rubric->name)->toBe('Annual Teaching Rubric')
        ->and($rubric->criteria)->toHaveCount(2)
        ->and($rubric->criteria[0]['descriptor_levels'])->toBe(['unsatisfactory', 'developing', 'proficient', 'outstanding']);

    Livewire::test(Index::class, ['school' => $f['school']])
        ->set('rubricName', 'Bad Rubric')
        ->set('rubricCriteria', 'Only one level: just-this-one')
        ->call('createRubric')
        ->assertHasErrors('rubricName');
});

it('scores every criterion on the chosen rubric and refuses an incomplete or invalid submission (mirrors BR-ACA-11-006)', function (): void {
    $f = appraisalRubricFixture();
    $appraiser = Staff::factory()->for($f['school'])->create();
    $staff = Staff::factory()->for($f['school'])->create();
    $rubric = StaffAppraisalRubric::factory()->for($f['school'])->create([
        'criteria' => [
            ['criterion' => 'Job knowledge', 'descriptor_levels' => ['unsatisfactory', 'developing', 'proficient', 'outstanding']],
            ['criterion' => 'Collaboration', 'descriptor_levels' => ['poor', 'good', 'excellent']],
        ],
    ]);

    $appraisal = app(CreateStaffAppraisalAction::class)->execute(new CreateStaffAppraisalData(
        schoolId: $f['school']->id, staffId: $staff->id, academicYearId: $f['year']->id,
        cycle: 'annual', appraiserStaffId: $appraiser->id, rubricId: $rubric->id,
    ));

    expect(fn () => app(SubmitSelfAssessmentAction::class)->execute(new SubmitSelfAssessmentData(
        appraisalId: $appraisal->id, selfAssessment: ['scores' => ['Job knowledge' => 'proficient']],
    )))->toThrow(InvalidArgumentException::class);

    expect(fn () => app(SubmitSelfAssessmentAction::class)->execute(new SubmitSelfAssessmentData(
        appraisalId: $appraisal->id, selfAssessment: ['scores' => ['Job knowledge' => 'proficient', 'Collaboration' => 'not-a-real-level']],
    )))->toThrow(InvalidArgumentException::class);

    $scored = app(SubmitSelfAssessmentAction::class)->execute(new SubmitSelfAssessmentData(
        appraisalId: $appraisal->id, selfAssessment: ['scores' => ['Job knowledge' => 'proficient', 'Collaboration' => 'good'], 'comments' => 'Solid year'],
    ));

    expect($scored->status)->toBe('self_assessment')
        ->and($scored->self_assessment['scores'])->toBe(['Job knowledge' => 'proficient', 'Collaboration' => 'good']);

    $appraised = app(SubmitAppraiserAssessmentAction::class)->execute(new SubmitAppraiserAssessmentData(
        appraisalId: $appraisal->id,
        appraiserAssessment: ['scores' => ['Job knowledge' => 'outstanding', 'Collaboration' => 'excellent']],
        overallRating: 'exceeds',
    ));

    expect($appraised->status)->toBe('appraiser_review')
        ->and($appraised->appraiser_assessment['scores']['Job knowledge'])->toBe('outstanding');
});

it('still accepts a free-text assessment for an appraisal with no rubric (backward compatible)', function (): void {
    $f = appraisalRubricFixture();
    $appraiser = Staff::factory()->for($f['school'])->create();
    $staff = Staff::factory()->for($f['school'])->create();

    $appraisal = app(CreateStaffAppraisalAction::class)->execute(new CreateStaffAppraisalData(
        schoolId: $f['school']->id, staffId: $staff->id, academicYearId: $f['year']->id,
        cycle: 'annual', appraiserStaffId: $appraiser->id,
    ));

    expect($appraisal->rubric_id)->toBeNull();

    $scored = app(SubmitSelfAssessmentAction::class)->execute(new SubmitSelfAssessmentData(
        appraisalId: $appraisal->id, selfAssessment: ['notes' => 'Good year'],
    ));

    expect($scored->self_assessment)->toBe(['notes' => 'Good year']);
});

it('refuses an appraisal created against a rubric from another school', function (): void {
    $f = appraisalRubricFixture();
    $appraiser = Staff::factory()->for($f['school'])->create();
    $staff = Staff::factory()->for($f['school'])->create();
    $foreignRubric = StaffAppraisalRubric::factory()->create();

    expect(fn () => app(CreateStaffAppraisalAction::class)->execute(new CreateStaffAppraisalData(
        schoolId: $f['school']->id, staffId: $staff->id, academicYearId: $f['year']->id,
        cycle: 'annual', appraiserStaffId: $appraiser->id, rubricId: $foreignRubric->id,
    )))->toThrow(InvalidArgumentException::class);
});

it('renders the rubric scoring form on Appraisal\\Show once a rubric is chosen', function (): void {
    $f = appraisalRubricFixture();
    $appraiser = Staff::factory()->for($f['school'])->create();
    $staff = Staff::factory()->for($f['school'])->create();
    $rubric = StaffAppraisalRubric::factory()->for($f['school'])->create([
        'criteria' => [['criterion' => 'Punctuality', 'descriptor_levels' => ['poor', 'good']]],
    ]);

    $appraisal = app(CreateStaffAppraisalAction::class)->execute(new CreateStaffAppraisalData(
        schoolId: $f['school']->id, staffId: $staff->id, academicYearId: $f['year']->id,
        cycle: 'annual', appraiserStaffId: $appraiser->id, rubricId: $rubric->id,
    ));

    $this->actingAs(appraisalRubricUser($f, 'people.staff.appraisal_manage'));

    Livewire::test(Show::class, ['school' => $f['school'], 'appraisal' => $appraisal])
        ->assertSee('Punctuality')
        ->set('selfScores.0', 'good')
        ->call('submitSelfAssessment')
        ->assertHasNoErrors();

    expect($appraisal->fresh()->self_assessment['scores'])->toBe(['Punctuality' => 'good']);
});
