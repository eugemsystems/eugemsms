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
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Wallet\Domain\Actions\CreateSpendPointAction;
use Modules\Wallet\Domain\Actions\CreateStudentWalletAction;
use Modules\Wallet\Domain\Actions\CreateWalletProductAction;
use Modules\Wallet\Domain\Actions\SetWalletControlsAction;
use Modules\Wallet\Domain\DataObjects\CreateSpendPointData;
use Modules\Wallet\Domain\DataObjects\CreateStudentWalletData;
use Modules\Wallet\Domain\DataObjects\CreateWalletProductData;
use Modules\Wallet\Domain\DataObjects\SetWalletControlsData;
use Modules\Wallet\Livewire\Pos\Terminal as PosTerminal;
use Modules\Wallet\Livewire\Products\Index as ProductsIndex;
use Modules\Wallet\Livewire\Reports\Reconciliation as ReportsReconciliation;
use Modules\Wallet\Livewire\Reports\Sales as ReportsSales;
use Modules\Wallet\Livewire\SpendPoints\Index as SpendPointsIndex;
use Modules\Wallet\Livewire\TermEnd\Process as TermEndProcess;
use Modules\Wallet\Livewire\Wallets\Index as WalletsIndex;
use Modules\Wallet\Livewire\Wallets\Show as WalletsShow;
use Modules\Wallet\Models\StudentWallet;

/**
 * Book H3 FIN-14 admin-UI pass. Own, distinctly-named fixture
 * (`walletAdminFixture`/`walletAdminUser`) — `fin14Fixture` already
 * exists in the sibling backend test file.
 *
 * @return array<string, mixed>
 */
function walletAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $user = User::factory()->create();

    foreach (['journal' => 'AWJ', 'wallet_sale' => 'AWS'] as $documentType => $prefix) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $documentType, pattern: $prefix.'/{SEQ:6}',
        ));
    }

    $liabilityAccount = Account::factory()->for($school)->liability()->create(['system_key' => 'wallet_liability']);
    $incomeAccount = Account::factory()->for($school)->income()->create();
    Account::factory()->for($school)->controlAccount('student')->create();

    $spendPoint = app(CreateSpendPointAction::class)->execute(new CreateSpendPointData(
        schoolId: $school->id, code: 'ATUCK', name: 'Admin Tuckshop', pointType: 'tuckshop',
        incomeAccountId: $incomeAccount->id, costCentreId: CostCentre::factory()->for($school)->create()->id,
        isFiscalisable: false,
    ));

    $chocolate = app(CreateWalletProductAction::class)->execute(new CreateWalletProductData(
        schoolId: $school->id, spendPointId: $spendPoint->id, code: 'ACHOC', name: 'Chocolate Bar',
        category: 'confectionery', priceMinor: 100, currency: 'USD', taxType: 'standard',
    ));

    $guardian = Guardian::factory()->for($school)->create();
    $student = Student::factory()->for($school)->create();
    StudentGuardian::factory()->create([
        'school_id' => $school->id, 'student_id' => $student->id, 'guardian_id' => $guardian->id,
        'is_fee_responsible' => true, 'is_primary_contact' => true,
    ]);

    $wallet = app(CreateStudentWalletAction::class)->execute(new CreateStudentWalletData(
        schoolId: $school->id, studentId: $student->id, currency: 'USD', liabilityAccountId: $liabilityAccount->id,
    ));

    return compact('school', 'year', 'term', 'user', 'spendPoint', 'chocolate', 'guardian', 'student', 'wallet');
}

/**
 * @param  array<string, mixed>  $f
 */
function walletAdminUser(array $f, string ...$permissionNames): User
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

it('refuses to mount the POS terminal for a user with no wallet.sell grant', function (): void {
    $f = walletAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(PosTerminal::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every wallet screen for a fully-permissioned user', function (): void {
    $f = walletAdminFixture();
    $user = walletAdminUser($f, 'wallet.sell', 'wallet.manage', 'wallet.view', 'wallet.report.view');

    Livewire::actingAs($user)->test(PosTerminal::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ProductsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(SpendPointsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(WalletsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(WalletsShow::class, ['school' => $f['school'], 'wallet' => $f['wallet']])->assertOk();
    Livewire::actingAs($user)->test(TermEndProcess::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ReportsReconciliation::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ReportsSales::class, ['school' => $f['school']])->assertOk();
});

it('refuses a blocked category at the POS with the reason surfaced to the operator (AC-FIN-14-002)', function (): void {
    $f = walletAdminFixture();
    $user = walletAdminUser($f, 'wallet.sell');

    app(SetWalletControlsAction::class)->execute(new SetWalletControlsData(
        walletId: $f['wallet']->id, setByGuardianId: $f['guardian']->id, blockedCategories: ['confectionery'],
    ));

    Livewire::actingAs($user)->test(PosTerminal::class, ['school' => $f['school']])
        ->set('spendPointId', $f['spendPoint']->id)
        ->set('productId', $f['chocolate']->id)
        ->set('quantity', '1')
        ->call('addLine')
        ->set('paymentMethod', 'wallet')
        ->set('studentId', $f['student']->id)
        ->call('submitSale')
        ->assertDispatched('toast', fn (string $name, array $params): bool => str_contains((string) $params['text'], 'confectionery') && $params['variant'] === 'danger');

    expect(StudentWallet::find($f['wallet']->id)->balance_minor)->toBe(0);
});
