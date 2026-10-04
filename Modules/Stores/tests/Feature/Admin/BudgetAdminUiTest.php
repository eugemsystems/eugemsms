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
use Modules\Stores\Domain\Actions\CreateBudgetAction;
use Modules\Stores\Domain\Actions\SubmitBudgetLineAction;
use Modules\Stores\Domain\DataObjects\CreateBudgetData;
use Modules\Stores\Domain\DataObjects\SubmitBudgetLineData;
use Modules\Stores\Livewire\Budget\Builder\Index as BuilderIndex;
use Modules\Stores\Livewire\Budget\Commitments\Index as CommitmentsIndex;
use Modules\Stores\Livewire\Budget\Consolidation\Review;
use Modules\Stores\Livewire\Budget\Forecast\Index as ForecastIndex;
use Modules\Stores\Livewire\Budget\Variance\Dashboard;
use Modules\Stores\Livewire\Budget\Virement\Create as VirementCreate;
use Modules\Stores\Livewire\Procurement\Orders\Index as OrdersIndex;
use Modules\Stores\Models\PurchaseOrder;
use Modules\Stores\Models\Supplier;

/**
 * Book H1 FIN-11 admin-UI pass ⭐. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, costCentre: CostCentre, expenseAccount: Account}
 */
function budgetAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);
    $costCentre = CostCentre::factory()->for($school)->create();
    $expenseAccount = Account::factory()->for($school)->expense()->create(['code' => 'KITCHEN-PROV']);

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}'));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'purchase_order', pattern: 'PO/{SEQ:6}', academicYearId: $year->id));

    return compact('school', 'year', 'term', 'costCentre', 'expenseAccount');
}

/**
 * Splits on the LAST dot for the action — see `.ai/rules/stores.md`.
 *
 * @param  array<string, mixed>  $f
 */
function budgetAdminUser(array $f, string ...$permissionNames): User
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

it('refuses to mount the variance dashboard for a user with no budget.view grant', function (): void {
    $f = budgetAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(Dashboard::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('commits budget immediately on PO approval through the real FIN-08 to FIN-11 event wiring (AC-FIN-11-001)', function (): void {
    $f = budgetAdminFixture();
    $preparer = budgetAdminUser($f, 'budget.manage');

    $budget = app(CreateBudgetAction::class)->execute(new CreateBudgetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Operating 2026',
        budgetType: 'operating', periodBasis: 'annual', currency: 'USD', preparedByUserId: $preparer->id,
    ));

    $line = app(SubmitBudgetLineAction::class)->execute(new SubmitBudgetLineData(
        budgetId: $budget->id, accountId: $f['expenseAccount']->id, costCentreId: $f['costCentre']->id,
        annualAmountMinor: 18000000, // USD 180,000
    ));

    expect($line->available_minor)->toBe(18000000);

    $supplier = Supplier::factory()->for($f['school'])->create();
    $buyer = budgetAdminUser($f, 'procurement.order.view', 'procurement.order.create');
    $approver = budgetAdminUser($f, 'procurement.order.view', 'procurement.order.approve');

    Livewire::actingAs($buyer)->test(OrdersIndex::class, ['school' => $f['school']])
        ->set('supplierId', $supplier->id)
        ->set('costCentreId', $f['costCentre']->id)
        ->set('budgetLineId', $line->id)
        ->set('lines.0.description', 'Maize meal')
        ->set('lines.0.quantity_ordered', '1')
        ->set('lines.0.unit', 'bag')
        ->set('lines.0.unit_price_minor', '2400000') // USD 24,000
        ->set('lines.0.expense_account_id', (string) $f['expenseAccount']->id)
        ->call('create')
        ->assertOk();

    $order = PurchaseOrder::where('supplier_id', $supplier->id)->first();
    expect($order)->not->toBeNull()->and($order->status)->toBe('pending_approval');

    Livewire::actingAs($approver)->test(OrdersIndex::class, ['school' => $f['school']])
        ->call('approve', $order->id)
        ->assertOk();

    expect($order->fresh()->status)->toBe('approved');

    // USD 180,000 - USD 24,000 = USD 156,000 available, immediately.
    expect($line->fresh()->available_minor)->toBe(15600000)
        ->and($line->fresh()->committed_minor)->toBe(2400000);
});

it('renders every budget screen for a fully-permissioned user', function (): void {
    $f = budgetAdminFixture();
    $admin = budgetAdminUser(
        $f,
        'budget.manage', 'budget.submit', 'budget.consolidate', 'budget.view',
        'budget.virement.request', 'budget.forecast.view', 'budget.forecast.manage',
    );

    $budget = app(CreateBudgetAction::class)->execute(new CreateBudgetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Operating 2026',
        budgetType: 'operating', periodBasis: 'annual', currency: 'USD', preparedByUserId: $admin->id,
    ));
    app(SubmitBudgetLineAction::class)->execute(new SubmitBudgetLineData(
        budgetId: $budget->id, accountId: $f['expenseAccount']->id, costCentreId: $f['costCentre']->id,
        annualAmountMinor: 1000000,
    ));

    Livewire::actingAs($admin)->test(BuilderIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Review::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Dashboard::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(CommitmentsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(VirementCreate::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(ForecastIndex::class, ['school' => $f['school']])->assertOk();
});
