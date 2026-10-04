<?php

use App\Models\User;
use Livewire\Livewire;
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
use Modules\People\Domain\Actions\LinkGuardianToStudentAction;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;
use Modules\People\Livewire\Guardians\Create as GuardiansCreate;
use Modules\People\Livewire\Guardians\Index as GuardiansIndex;
use Modules\People\Livewire\Students\Guardians as StudentsGuardians;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * Book C PPL-03 §8 admin UI — Guardian directory/profile/create and
 * the student-scoped relationship editor. Own, distinctly-named
 * fixture — see `StudentsAdminUiTest`'s own note on why a Pest helper
 * defined in one test file can't be relied on from another run
 * standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, section: SchoolSection, gradeLevel: GradeLevel, student: Student}
 */
function guardiansAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $student = Student::factory()->for($school)->create([
        'section_id' => $section->id, 'grade_level_id' => $gradeLevel->id,
    ]);

    return compact('school', 'year', 'term', 'section', 'gradeLevel', 'student');
}

function guardiansAdminUser(School $school, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

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
            userId: $user->id, schoolId: $school->id, grants: $grants,
        ));
    }

    return $user;
}

it('serves Guardians\\Index through a real routed request', function (): void {
    $f = guardiansAdminFixture();
    $user = guardiansAdminUser($f['school'], 'people.guardians.view');

    $this->actingAs($user)
        ->get(route('people.guardians.index', $f['school']))
        ->assertOk();
});

it('refuses Guardians\\Index to a user without people.guardians.view', function (): void {
    $f = guardiansAdminFixture();
    $user = guardiansAdminUser($f['school']);

    Livewire::actingAs($user)->test(GuardiansIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('creates an individual guardian and redirects to their profile', function (): void {
    $f = guardiansAdminFixture();
    $user = guardiansAdminUser($f['school'], 'people.guardians.create');

    Livewire::actingAs($user)->test(GuardiansCreate::class, ['school' => $f['school']])
        ->set('guardianType', 'individual')
        ->set('firstName', 'Grace')
        ->set('lastName', 'Moyo')
        ->set('primaryPhone', '+263771234567')
        ->call('save')
        ->assertRedirect();

    expect(Guardian::where('school_id', $f['school']->id)->where('first_name', 'Grace')->where('last_name', 'Moyo')->exists())->toBeTrue();
});

it('creates an organisation guardian', function (): void {
    $f = guardiansAdminFixture();
    $user = guardiansAdminUser($f['school'], 'people.guardians.create');

    Livewire::actingAs($user)->test(GuardiansCreate::class, ['school' => $f['school']])
        ->set('guardianType', 'organisation')
        ->set('organisationName', 'Delta Corporation')
        ->set('organisationType', 'employer')
        ->call('save')
        ->assertRedirect();

    $guardian = Guardian::where('school_id', $f['school']->id)->where('organisation_name', 'Delta Corporation')->sole();
    expect($guardian->guardian_type)->toBe('organisation')
        ->and($guardian->organisation_type)->toBe('employer');
});

it('links a guardian to a learner with explicit rights (BR-PPL-03-005)', function (): void {
    $f = guardiansAdminFixture();
    $user = guardiansAdminUser($f['school'], 'people.guardians.manage_relationships');
    $guardian = Guardian::factory()->for($f['school'])->create();

    Livewire::actingAs($user)->test(StudentsGuardians::class, ['school' => $f['school'], 'student' => $f['student']])
        ->set('guardianId', $guardian->id)
        ->set('relationship', 'mother')
        ->set('isFeeResponsible', true)
        ->set('isPrimaryContact', true)
        ->call('link');

    $link = StudentGuardian::where('student_id', $f['student']->id)->where('guardian_id', $guardian->id)->sole();
    expect($link->is_fee_responsible)->toBeTrue()
        ->and($link->is_primary_contact)->toBeTrue()
        ->and($link->may_collect_learner)->toBeFalse();
});

it('refuses to deactivate a learner\'s last fee-responsible guardian (BR-PPL-03-004/022)', function (): void {
    $f = guardiansAdminFixture();
    $user = guardiansAdminUser($f['school'], 'people.guardians.manage_relationships');
    $guardian = Guardian::factory()->for($f['school'])->create();

    $link = app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $f['student']->id, guardianId: $guardian->id, relationship: 'mother', createdByUserId: $user->id, isFeeResponsible: true,
    ));

    Livewire::actingAs($user)->test(StudentsGuardians::class, ['school' => $f['school'], 'student' => $f['student']])
        ->call('deactivate', $link->id)
        ->assertDispatched('toast', variant: 'danger');

    expect($link->fresh()->status)->toBe('active');
});

it('deactivates a guardian relationship once another fee-responsible guardian exists', function (): void {
    $f = guardiansAdminFixture();
    $user = guardiansAdminUser($f['school'], 'people.guardians.manage_relationships');
    $first = Guardian::factory()->for($f['school'])->create();
    $second = Guardian::factory()->for($f['school'])->create();

    $firstLink = app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $f['student']->id, guardianId: $first->id, relationship: 'mother', createdByUserId: $user->id, isFeeResponsible: true,
    ));
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $f['student']->id, guardianId: $second->id, relationship: 'father', createdByUserId: $user->id, isFeeResponsible: true,
    ));

    Livewire::actingAs($user)->test(StudentsGuardians::class, ['school' => $f['school'], 'student' => $f['student']])
        ->call('deactivate', $firstLink->id)
        ->assertDispatched('toast', variant: 'success');

    expect($firstLink->fresh()->status)->toBe('inactive');
});
