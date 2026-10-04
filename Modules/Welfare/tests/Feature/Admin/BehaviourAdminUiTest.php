<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\RecordBehaviourAction;
use Modules\Welfare\Domain\DataObjects\RecordBehaviourData;
use Modules\Welfare\Livewire\Behaviour\Board;
use Modules\Welfare\Livewire\Behaviour\Categories;
use Modules\Welfare\Livewire\Behaviour\Record;
use Modules\Welfare\Livewire\Sanctions\Issue;
use Modules\Welfare\Models\BehaviourCategory;
use Modules\Welfare\Models\BehaviourRecord;
use Modules\Welfare\Models\Sanction;
use Modules\Welfare\Models\SanctionType;

/**
 * Book G BRD-07 admin-UI pass. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, student: Student}
 */
function behaviourAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $student = Student::factory()->for($school)->create();

    return compact('school', 'year', 'term', 'student');
}

/**
 * @param  array<string, mixed>  $f
 */
function behaviourAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $parts = explode('.', $permissionName);
        $moduleCode = strtoupper($parts[0]);
        $action = array_pop($parts);
        $resource = implode('.', array_slice($parts, 1)) ?: $action;

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => $moduleCode, 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

it('records both polarities through the same screen, with the positive category listed', function (): void {
    $f = behaviourAdminFixture();
    $teacher = behaviourAdminUser($f, 'behaviour.record');
    $category = BehaviourCategory::factory()->create(['school_id' => $f['school']->id, 'polarity' => 'positive', 'default_points' => 5, 'is_active' => true]);

    Livewire::actingAs($teacher)->test(Record::class, ['school' => $f['school']])
        ->assertSee($category->name)
        ->set('studentId', $f['student']->id)
        ->set('categoryId', $category->id)
        ->set('description', 'Helped a younger learner with homework.')
        ->call('record')
        ->assertOk();

    $record = BehaviourRecord::where('student_id', $f['student']->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->polarity)->toBe('positive')
        ->and($record->points)->toBe(5);
});

it('masks a safeguarding-paused record detail on the behaviour board, showing only that it is under review', function (): void {
    $f = behaviourAdminFixture();
    $teacher = behaviourAdminUser($f, 'behaviour.record', 'behaviour.view');
    $triggerCategory = BehaviourCategory::factory()->create([
        'school_id' => $f['school']->id, 'polarity' => 'negative', 'is_safeguarding_trigger' => true, 'is_active' => true,
    ]);

    app(RecordBehaviourAction::class)->execute(new RecordBehaviourData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        studentId: $f['student']->id, categoryId: $triggerCategory->id,
        description: 'Confidential safeguarding-relevant detail that must never reach the board.',
        occurredAt: Carbon::now(), reportedByUserId: $teacher->id,
    ));

    Livewire::actingAs($teacher)->test(Board::class, ['school' => $f['school']])
        ->assertOk()
        ->assertSee('Under review')
        ->assertDontSee('Confidential safeguarding-relevant detail that must never reach the board.');
});

it('refuses to deactivate the last active safeguarding-trigger category, surfaced as a toast', function (): void {
    $f = behaviourAdminFixture();
    $manager = behaviourAdminUser($f, 'behaviour.manage');
    $onlyTrigger = BehaviourCategory::factory()->create(['school_id' => $f['school']->id, 'is_safeguarding_trigger' => true, 'is_active' => true]);

    Livewire::actingAs($manager)->test(Categories::class, ['school' => $f['school']])
        ->call('deactivate', $onlyTrigger->id)
        ->assertOk();

    expect($onlyTrigger->refresh()->is_active)->toBeTrue();
});

it('refuses to issue a committee-requiring sanction without a disciplinary committee record', function (): void {
    $f = behaviourAdminFixture();
    $head = behaviourAdminUser($f, 'behaviour.sanction.issue');
    $sanctionType = SanctionType::factory()->create(['school_id' => $f['school']->id, 'requires_committee' => true, 'is_active' => true]);

    Livewire::actingAs($head)->test(Issue::class, ['school' => $f['school']])
        ->set('studentId', $f['student']->id)
        ->set('sanctionTypeId', $sanctionType->id)
        ->set('reason', 'Serious repeated misconduct.')
        ->call('issue')
        ->assertOk();

    expect(Sanction::where('student_id', $f['student']->id)->count())->toBe(0);
});
