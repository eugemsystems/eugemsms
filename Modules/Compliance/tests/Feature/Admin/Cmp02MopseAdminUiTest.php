<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Compliance\Livewire\Mopse\InspectionPack\Index as InspectionPackIndex;
use Modules\Compliance\Livewire\Mopse\SchoolReturns\Index as SchoolReturnsIndex;
use Modules\Compliance\Models\StatutorySchoolReturn;
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

/**
 * Book H3 CMP-02 admin-UI pass. Own, distinctly-named fixture
 * (`cmp02AdminFixture`/`cmp02AdminUser`) — `cmp02Fixture` already
 * exists in the sibling backend test file.
 *
 * @return array<string, mixed>
 */
function cmp02AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $user = User::factory()->create();

    Student::factory()->for($school)->count(3)->create(['status' => 'active']);

    return compact('school', 'year', 'term', 'user');
}

/**
 * @param  array<string, mixed>  $f
 */
function cmp02AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $lastDot = strrpos($permissionName, '.');
        $moduleCode = strtoupper(substr($permissionName, 0, strpos($permissionName, '.')));
        $action = substr($permissionName, $lastDot + 1);
        $resource = substr($permissionName, strpos($permissionName, '.') + 1, $lastDot - strpos($permissionName, '.') - 1);
        $resource = $resource !== '' ? $resource : $action;

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

it('refuses to mount the school returns screen for a user with no mopse.view grant', function (): void {
    $f = cmp02AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(SchoolReturnsIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every MoPSE screen for a fully-permissioned user', function (): void {
    $f = cmp02AdminFixture();
    $user = cmp02AdminUser($f, 'mopse.manage', 'mopse.view');

    Livewire::actingAs($user)->test(SchoolReturnsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(InspectionPackIndex::class, ['school' => $f['school']])->assertOk();
});

it('reports data quality issues before generation and freezes the snapshot on regeneration (AC-CMP-02-001/002)', function (): void {
    $f = cmp02AdminFixture();
    $user = cmp02AdminUser($f, 'mopse.manage', 'mopse.view');

    Livewire::actingAs($user)->test(SchoolReturnsIndex::class, ['school' => $f['school']])
        ->call('runQualityChecks')
        ->assertDispatched('toast');

    Livewire::actingAs($user)->test(SchoolReturnsIndex::class, ['school' => $f['school']])
        ->set('returnType', 'annual_schools_census')
        ->set('periodReference', '2026-T1')
        ->set('dueDate', now()->addMonth()->toDateString())
        ->set('authority', 'MoPSE District')
        ->call('generate');

    $return = StatutorySchoolReturn::where('school_id', $f['school']->id)->where('period_reference', '2026-T1')->first();
    expect($return)->not->toBeNull();
    $firstSnapshot = $return->data_snapshot;

    // Add another student, then regenerate for the same period — the frozen snapshot must not change.
    Student::factory()->for($f['school'])->create(['status' => 'active']);

    Livewire::actingAs($user)->test(SchoolReturnsIndex::class, ['school' => $f['school']])
        ->set('returnType', 'annual_schools_census')
        ->set('periodReference', '2026-T1')
        ->set('dueDate', now()->addMonth()->toDateString())
        ->set('authority', 'MoPSE District')
        ->call('generate');

    expect($return->fresh()->data_snapshot)->toBe($firstSnapshot);
});
