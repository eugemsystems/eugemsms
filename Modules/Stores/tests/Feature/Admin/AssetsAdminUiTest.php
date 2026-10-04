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
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Domain\Actions\CapitalizeAssetAction;
use Modules\Stores\Domain\Actions\CreateVerificationRoundAction;
use Modules\Stores\Domain\DataObjects\CapitalizeAssetData;
use Modules\Stores\Livewire\Assets\Depreciation\Run as DepreciationRun;
use Modules\Stores\Livewire\Assets\Disposal\Create as DisposalCreate;
use Modules\Stores\Livewire\Assets\Insurance\Index as InsuranceIndex;
use Modules\Stores\Livewire\Assets\Register\Index as RegisterIndex;
use Modules\Stores\Livewire\Assets\Register\Show as RegisterShow;
use Modules\Stores\Livewire\Assets\Reports\Reconciliation;
use Modules\Stores\Livewire\Assets\Verification\Discrepancies;
use Modules\Stores\Livewire\Assets\Verification\Round as VerificationRound;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\AssetVerification;

/**
 * Book H1 FIN-10 admin-UI pass. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, category: AssetCategory, costCentre: CostCentre}
 */
function assetsAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);
    $costCentre = CostCentre::factory()->for($school)->create();

    $assetAccount = Account::factory()->for($school)->create(['code' => 'ASSET1']);
    $accumDep = Account::factory()->for($school)->create(['code' => 'ACCUMDEP']);
    $depExpense = Account::factory()->for($school)->expense()->create(['code' => 'DEPEXP']);
    $disposal = Account::factory()->for($school)->create(['code' => 'DISPOSAL']);

    $category = AssetCategory::factory()->for($school)->create([
        'asset_account_id' => $assetAccount->id,
        'accum_depreciation_account_id' => $accumDep->id,
        'depreciation_expense_account_id' => $depExpense->id,
        'disposal_account_id' => $disposal->id,
        'default_method' => 'straight_line',
        'default_useful_life_years' => 5,
        'default_residual_percent' => 0,
    ]);

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}'));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'fixed_asset', pattern: 'AST/{SEQ:6}'));

    return compact('school', 'year', 'term', 'category', 'costCentre');
}

/**
 * Splits on the LAST dot for the action — see `.ai/rules/stores.md`.
 *
 * @param  array<string, mixed>  $f
 */
function assetsAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $lastDot = strrpos($permissionName, '.');
        $firstDot = strpos($permissionName, '.');
        $moduleCode = strtoupper(substr($permissionName, 0, $firstDot));
        $action = substr($permissionName, $lastDot + 1);
        $resource = substr($permissionName, $firstDot + 1, $lastDot - $firstDot - 1);
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

it('refuses to mount the asset register for a user with no assets.view grant', function (): void {
    $f = assetsAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(RegisterIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('cannot write off a not-found asset by the same user who recorded the failed scan (BR-FIN-10-012, AC-FIN-10-005)', function (): void {
    $f = assetsAdminFixture();
    $scanner = assetsAdminUser($f, 'assets.verify');

    $asset = app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'Minibus', acquisitionDate: Carbon::now(),
        acquisitionCostMinor: 1000000, currency: 'USD', acquisitionSource: 'purchase',
        costCentreId: $f['costCentre']->id, contraAccountId: $f['category']->disposal_account_id,
        performedByUserId: $scanner->id,
    ));

    app(CreateVerificationRoundAction::class)->execute($f['school']->id, '2026-Q1');
    $verification = AssetVerification::where('asset_id', $asset->id)->first();

    Livewire::actingAs($scanner)->test(VerificationRound::class, ['school' => $f['school']])
        ->set('verificationRound', '2026-Q1')
        ->call('scan', $verification->id, false)
        ->assertOk();

    expect($verification->fresh()->status)->toBe('not_found');

    // The SAME user who recorded the failed scan cannot also write it off.
    Livewire::actingAs($scanner)->test(Discrepancies::class, ['school' => $f['school']])
        ->set("reasons.{$verification->id}", 'Not located anywhere on campus.')
        ->call('writeOff', $verification->id)
        ->assertOk();

    expect($asset->fresh()->status)->not->toBe('written_off');

    // A DIFFERENT user can.
    $approver = assetsAdminUser($f, 'assets.verify');
    Livewire::actingAs($approver)->test(Discrepancies::class, ['school' => $f['school']])
        ->set("reasons.{$verification->id}", 'Confirmed missing after search — written off.')
        ->call('writeOff', $verification->id)
        ->assertOk();

    expect($asset->fresh()->status)->toBe('written_off');
});

it('renders every asset screen for a fully-permissioned user', function (): void {
    $f = assetsAdminFixture();
    $admin = assetsAdminUser(
        $f,
        'assets.view', 'assets.manage', 'assets.depreciation.run', 'assets.transfer',
        'assets.verify', 'assets.dispose', 'assets.insurance.manage', 'assets.report.view',
    );

    $asset = app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'Laptop', acquisitionDate: Carbon::now(),
        acquisitionCostMinor: 80000, currency: 'USD', acquisitionSource: 'purchase',
        costCentreId: $f['costCentre']->id, contraAccountId: $f['category']->disposal_account_id,
        performedByUserId: $admin->id,
    ));

    Livewire::actingAs($admin)->test(RegisterIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(RegisterShow::class, ['school' => $f['school'], 'asset' => $asset])->assertOk();
    Livewire::actingAs($admin)->test(DepreciationRun::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(VerificationRound::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Discrepancies::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(DisposalCreate::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(InsuranceIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Reconciliation::class, ['school' => $f['school']])->assertOk();
});
