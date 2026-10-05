<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Security\Domain\Actions\ApproveContractorAction;
use Modules\Security\Domain\Actions\CreateContractorAction;
use Modules\Security\Domain\Actions\CreateContractorWorkerAction;
use Modules\Security\Domain\DataObjects\ApproveContractorData;
use Modules\Security\Domain\DataObjects\CreateContractorData;
use Modules\Security\Domain\DataObjects\CreateContractorWorkerData;
use Modules\Security\Livewire\Contractors\Index as ContractorsIndex;
use Modules\Security\Livewire\Drills\Index as DrillsIndex;
use Modules\Security\Livewire\Keys\Index as KeysIndex;
use Modules\Security\Livewire\LostProperty\Index as LostPropertyIndex;
use Modules\Security\Livewire\Muster\Index as MusterIndex;
use Modules\Security\Livewire\OccurrenceBook\Index as OccurrenceBookIndex;
use Modules\Security\Livewire\Patrols\Index as PatrolsIndex;
use Modules\Security\Models\ContractorSiteVisit;

/**
 * Book H2 OPS-06 admin-UI pass. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term}
 */
function securityAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'occurrence_book', pattern: '{SEQ:6}'));

    return compact('school', 'year', 'term');
}

/**
 * @param  array<string, mixed>  $f
 */
function securityAdminUser(array $f, string ...$permissionNames): User
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

it('refuses to mount the muster screen for a user with no security.muster grant', function (): void {
    $f = securityAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(MusterIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every security screen for a fully-permissioned user', function (): void {
    $f = securityAdminFixture();
    $user = securityAdminUser(
        $f,
        'security.muster', 'security.occurrence.record', 'security.patrol.manage',
        'security.contractor.manage', 'security.key.manage', 'security.manage', 'security.drill.manage',
    );

    Livewire::actingAs($user)->test(MusterIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(OccurrenceBookIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(PatrolsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ContractorsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(KeysIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(LostPropertyIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(DrillsIndex::class, ['school' => $f['school']])->assertOk();
});

it('refuses gate access for a contractor worker with no police clearance on file, with no override available (AC-OPS-06-004)', function (): void {
    $f = securityAdminFixture();
    $guard = securityAdminUser($f, 'security.contractor.manage');

    $contractor = app(CreateContractorAction::class)->execute(new CreateContractorData(
        schoolId: $f['school']->id, companyName: 'Acme Electrical',
    ));

    app(ApproveContractorAction::class)->execute($contractor->id, new ApproveContractorData(
        insuranceExpiresOn: Carbon::now()->addYear(),
        safetyInductionOn: Carbon::now()->subDay(),
        inductionValidUntil: Carbon::now()->addYear(),
        approvedByUserId: $guard->id,
    ));

    $worker = app(CreateContractorWorkerAction::class)->execute(new CreateContractorWorkerData(
        schoolId: $f['school']->id, contractorId: $contractor->id, fullName: 'John Worker',
        inductionCompletedOn: Carbon::now()->subDay(),
        // No police clearance recorded — BR-OPS-06-002 blocks gate access outright.
    ));

    expect($worker->is_cleared)->toBeFalse();

    Livewire::actingAs($guard)->test(ContractorsIndex::class, ['school' => $f['school']])
        ->set('gateWorkerId', $worker->id)
        ->call('signIn')
        ->assertSet('gateResult', 'REFUSED')
        ->assertSet('gateReason', fn (?string $reason): bool => $reason !== null && str_contains($reason, 'police clearance'));

    expect(ContractorSiteVisit::where('contractor_worker_id', $worker->id)->count())->toBe(0);
});
