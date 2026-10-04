<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Livewire\Students\ChangeAttribute;
use Modules\People\Livewire\Students\ChangeStatus;
use Modules\People\Livewire\Students\Create as StudentsCreate;
use Modules\People\Livewire\Students\Duplicates;
use Modules\People\Livewire\Students\Edit as StudentsEdit;
use Modules\People\Livewire\Students\Index as StudentsIndex;
use Modules\People\Models\Student;
use Modules\People\Models\StudentAttributeChange;

/**
 * Book C PPL-01 §8 admin UI — Student Information System. Own,
 * distinctly-named fixture — see `GeneralLedgerAdminUiTest`'s own
 * note on why a Pest helper defined in one test file can't be relied
 * on from another run standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, section: SchoolSection, gradeLevel: GradeLevel}
 */
function studentsAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    return compact('school', 'year', 'term', 'section', 'gradeLevel');
}

function studentsAdminUser(School $school, string ...$permissionNames): User
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

it('serves Students\\Index through a real routed request', function (): void {
    $f = studentsAdminFixture();
    $user = studentsAdminUser($f['school'], 'people.students.view');

    $this->actingAs($user)
        ->get(route('people.students.index', $f['school']))
        ->assertOk();
});

it('refuses Students\\Index to a user without people.students.view', function (): void {
    $f = studentsAdminFixture();
    $user = studentsAdminUser($f['school']);

    Livewire::actingAs($user)->test(StudentsIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('surfaces a possible duplicate before creating, and creates once confirmed (BR-PPL-01-009)', function (): void {
    $f = studentsAdminFixture();
    $user = studentsAdminUser($f['school'], 'people.students.create');

    Student::factory()->for($f['school'])->create([
        'first_name' => 'Tendai', 'last_name' => 'Moyo', 'date_of_birth' => '2015-03-10',
        'section_id' => $f['section']->id, 'grade_level_id' => $f['gradeLevel']->id,
    ]);

    $component = Livewire::actingAs($user)->test(StudentsCreate::class, ['school' => $f['school']])
        ->set('firstName', 'Tendai')
        ->set('lastName', 'Moyo')
        ->set('dateOfBirth', '2015-03-10')
        ->call('check');

    expect($component->get('duplicates'))->toHaveCount(1)
        ->and($component->get('hasCheckedDuplicates'))->toBeTrue();

    $component->set('sectionId', $f['section']->id)
        ->set('gradeLevelId', $f['gradeLevel']->id)
        ->call('save')
        ->assertRedirect();

    expect(Student::where('school_id', $f['school']->id)->where('first_name', 'Tendai')->count())->toBe(2);
});

it('changes a learner\'s non-billing profile fields without touching billing attributes', function (): void {
    $f = studentsAdminFixture();
    $user = studentsAdminUser($f['school'], 'people.students.update');
    $student = Student::factory()->for($f['school'])->create([
        'section_id' => $f['section']->id, 'grade_level_id' => $f['gradeLevel']->id,
    ]);

    Livewire::actingAs($user)->test(StudentsEdit::class, ['school' => $f['school'], 'student' => $student])
        ->set('city', 'Harare')
        ->set('suburb', 'Borrowdale')
        ->call('save')
        ->assertRedirect();

    expect($student->fresh()->city)->toBe('Harare')
        ->and($student->fresh()->enrolment_type)->toBe('FULL_TIME');
});

it('refuses Students\\Edit to a user without people.students.update', function (): void {
    $f = studentsAdminFixture();
    $user = studentsAdminUser($f['school']);
    $student = Student::factory()->for($f['school'])->create([
        'section_id' => $f['section']->id, 'grade_level_id' => $f['gradeLevel']->id,
    ]);

    Livewire::actingAs($user)->test(StudentsEdit::class, ['school' => $f['school'], 'student' => $student])
        ->assertForbidden();
});

it('changes a billing attribute through the single authorised path, writing an append-only history row (BR-PPL-01-004)', function (): void {
    $f = studentsAdminFixture();
    $user = studentsAdminUser($f['school'], 'people.students.change_billing_attribute');
    $student = Student::factory()->for($f['school'])->create([
        'section_id' => $f['section']->id, 'grade_level_id' => $f['gradeLevel']->id, 'residency' => 'DAY',
    ]);
    $student->enrolments()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'section_id' => $f['section']->id, 'grade_level_id' => $f['gradeLevel']->id, 'enrolment_type' => 'FULL_TIME',
        'residency' => 'DAY', 'status' => 'active', 'started_on' => now()->toDateString(), 'is_repeat' => false,
    ]);

    Livewire::actingAs($user)->test(ChangeAttribute::class, ['school' => $f['school'], 'student' => $student])
        ->set('attribute', 'residency')
        ->set('newValue', 'BOARDER')
        ->call('previewImpact')
        ->call('save')
        ->assertRedirect();

    expect($student->fresh()->residency)->toBe('BOARDER')
        ->and(StudentAttributeChange::where('student_id', $student->id)->where('attribute', 'residency')->sole()->new_value)->toBe('BOARDER');
});

it('withdraws a learner and later readmits them under the same admission number (BR-PPL-01-002/013)', function (): void {
    $f = studentsAdminFixture();
    $user = studentsAdminUser($f['school'], 'people.students.change_status');
    $student = Student::factory()->for($f['school'])->create([
        'section_id' => $f['section']->id, 'grade_level_id' => $f['gradeLevel']->id,
    ]);
    $admissionNumber = $student->admission_number;

    Livewire::actingAs($user)->test(ChangeStatus::class, ['school' => $f['school'], 'student' => $student])
        ->set('newStatus', 'withdrawn')
        ->set('exitedOn', now()->toDateString())
        ->call('save');

    expect($student->fresh()->status)->toBe('withdrawn');

    Livewire::actingAs($user)->test(ChangeStatus::class, ['school' => $f['school'], 'student' => $student->fresh()])
        ->set('newStatus', 'active')
        ->call('save');

    expect($student->fresh()->status)->toBe('active')
        ->and($student->fresh()->admission_number)->toBe($admissionNumber);
});

it('scans for a possible duplicate learner without merging anything', function (): void {
    $f = studentsAdminFixture();
    $user = studentsAdminUser($f['school'], 'people.students.merge');
    $student = Student::factory()->for($f['school'])->create([
        'first_name' => 'Rudo', 'last_name' => 'Chikosi', 'date_of_birth' => '2014-06-01',
        'section_id' => $f['section']->id, 'grade_level_id' => $f['gradeLevel']->id,
    ]);

    Livewire::actingAs($user)->test(Duplicates::class, ['school' => $f['school']])
        ->set('firstName', 'Rudo')
        ->set('lastName', 'Chikosi')
        ->set('dateOfBirth', '2014-06-01')
        ->call('scan');

    expect(Student::where('id', $student->id)->count())->toBe(1);
});
