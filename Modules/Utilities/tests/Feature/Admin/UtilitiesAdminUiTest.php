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
use Modules\Utilities\Domain\Actions\CreateMeterAction;
use Modules\Utilities\Domain\Actions\CreateUtilityAccountAction;
use Modules\Utilities\Domain\Actions\PurchasePrepaidTokenAction;
use Modules\Utilities\Domain\DataObjects\CreateMeterData;
use Modules\Utilities\Domain\DataObjects\CreateUtilityAccountData;
use Modules\Utilities\Domain\DataObjects\PurchasePrepaidTokenData;
use Modules\Utilities\Livewire\Accounts\Index as AccountsIndex;
use Modules\Utilities\Livewire\Dashboard\Index as DashboardIndex;
use Modules\Utilities\Livewire\GeneratorRuns\Index as GeneratorRunsIndex;
use Modules\Utilities\Livewire\Generators\Index as GeneratorsIndex;
use Modules\Utilities\Livewire\LoadShedding\Index as LoadSheddingIndex;
use Modules\Utilities\Livewire\Meters\Index as MetersIndex;
use Modules\Utilities\Livewire\Readings\Index as ReadingsIndex;
use Modules\Utilities\Livewire\Solar\Index as SolarIndex;
use Modules\Utilities\Livewire\Tokens\Index as TokensIndex;
use Modules\Utilities\Livewire\Water\Index as WaterIndex;
use Modules\Utilities\Models\PrepaidTokenPurchase;

/**
 * Book H2 OPS-04 admin-UI pass. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, costCentre: CostCentre, account: Account}
 */
function utilitiesAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);

    $costCentre = CostCentre::factory()->for($school)->create();
    $account = Account::factory()->for($school)->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}'));

    return compact('school', 'year', 'term', 'costCentre', 'account');
}

/**
 * @param  array<string, mixed>  $f
 */
function utilitiesAdminUser(array $f, string ...$permissionNames): User
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

it('refuses to mount the tokens screen for a user with no utilities.token.record grant', function (): void {
    $f = utilitiesAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(TokensIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every utilities screen for a fully-permissioned user', function (): void {
    $f = utilitiesAdminFixture();
    $user = utilitiesAdminUser(
        $f,
        'utilities.manage', 'utilities.token.record', 'utilities.read',
        'utilities.generator.manage', 'utilities.generator.record', 'utilities.water.manage', 'utilities.report.view',
    );

    Livewire::actingAs($user)->test(AccountsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(MetersIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(TokensIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ReadingsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(GeneratorsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(GeneratorRunsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(SolarIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(WaterIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(LoadSheddingIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(DashboardIndex::class, ['school' => $f['school']])->assertOk();
});

it('alerts and queues a token purchased and never credited past the configured window (AC-OPS-04-001)', function (): void {
    $f = utilitiesAdminFixture();
    $manager = utilitiesAdminUser($f, 'utilities.manage', 'utilities.token.record');

    $account = app(CreateUtilityAccountAction::class)->execute(new CreateUtilityAccountData(
        schoolId: $f['school']->id, utilityType: 'electricity', provider: 'ZESA', accountNumber: 'ACC-1',
        billingMode: 'prepaid', costCentreId: $f['costCentre']->id, expenseAccountId: $f['account']->id,
    ));

    $meter = app(CreateMeterAction::class)->execute(new CreateMeterData(
        schoolId: $f['school']->id, utilityAccountId: $account->id, meterNumber: 'MTR-1',
        meterType: 'electricity_prepaid', location: 'Main block', servesScope: 'whole_school', unit: 'kWh',
    ));

    $contra = Account::factory()->for($f['school'])->create();

    $purchase = app(PurchasePrepaidTokenAction::class)->execute(new PurchasePrepaidTokenData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, meterId: $meter->id,
        purchasedAt: Carbon::now()->subHours(30), tokenNumber: '00000000000000000001', amountPaidMinor: 20000,
        currency: 'USD', unitsPurchased: 150, prepaidAssetAccountId: $f['account']->id, contraAccountId: $contra->id,
        purchasedByUserId: $manager->id,
    ));

    expect($purchase->credit_confirmed)->toBeFalse();

    Livewire::actingAs($manager)->test(TokensIndex::class, ['school' => $f['school']])
        ->call('checkUncredited')
        ->assertSet('uncreditedMeters', fn (array $meters): bool => count($meters) === 1);

    expect(PrepaidTokenPurchase::where('id', $purchase->id)->where('status', 'purchased')->exists())->toBeTrue();
});
