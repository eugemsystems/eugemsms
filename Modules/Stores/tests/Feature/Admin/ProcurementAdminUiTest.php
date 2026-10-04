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
use Modules\Stores\Livewire\Procurement\Invoices\MatchReview;
use Modules\Stores\Livewire\Procurement\Invoices\Register;
use Modules\Stores\Livewire\Procurement\Orders\Index as OrdersIndex;
use Modules\Stores\Livewire\Procurement\Payments\Run;
use Modules\Stores\Livewire\Procurement\Quotations\Compare;
use Modules\Stores\Livewire\Procurement\Receipts\Create as GrnCreate;
use Modules\Stores\Livewire\Procurement\Reports\Index as ReportsIndex;
use Modules\Stores\Livewire\Procurement\Requisitions\Index as RequisitionsIndex;
use Modules\Stores\Livewire\Procurement\Suppliers\BankChange;
use Modules\Stores\Livewire\Procurement\Suppliers\Clearances;
use Modules\Stores\Livewire\Procurement\Suppliers\Index as SuppliersIndex;
use Modules\Stores\Livewire\Procurement\Suppliers\Show as SupplierShow;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierInvoice;

/**
 * Book H1 FIN-08 admin-UI pass 🇿🇼. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, costCentre: CostCentre, controlAccount: Account}
 */
function procurementAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);
    $costCentre = CostCentre::factory()->for($school)->create();
    $controlAccount = Account::factory()->for($school)->liability()->controlAccount('supplier')->create(['code' => 'CREDITORS']);

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}'));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'purchase_requisition', pattern: 'PR/{SEQ:6}', academicYearId: $year->id));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'quotation_request', pattern: 'QR/{SEQ:6}'));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'purchase_order', pattern: 'PO/{SEQ:6}', academicYearId: $year->id));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'goods_received_note', pattern: 'GRN/{SEQ:6}', academicYearId: $year->id));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'supplier_payment', pattern: 'PAY/{SEQ:6}'));

    return compact('school', 'year', 'term', 'costCentre', 'controlAccount');
}

/**
 * Splits on the LAST dot for the action — see `.ai/rules/stores.md`.
 *
 * @param  array<string, mixed>  $f
 */
function procurementAdminUser(array $f, string ...$permissionNames): User
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

it('refuses to mount the supplier register for a user with no procurement.supplier.view grant', function (): void {
    $f = procurementAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(SuppliersIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('creates a supplier pending approval and refuses the creator approving it themselves (BR-FIN-08-001)', function (): void {
    $f = procurementAdminFixture();
    $clerk = procurementAdminUser($f, 'procurement.supplier.view', 'procurement.supplier.manage', 'procurement.supplier.approve');

    Livewire::actingAs($clerk)->test(SuppliersIndex::class, ['school' => $f['school']])
        ->set('code', 'SUP1')->set('name', 'Acme Traders')->set('preferredCurrency', 'USD')
        ->set('controlAccountId', $f['controlAccount']->id)
        ->call('create')->assertOk();

    $supplier = Supplier::where('code', 'SUP1')->first();
    expect($supplier)->not->toBeNull()->and($supplier->status)->toBe('pending_approval');

    // The same user who created it cannot approve it — ApproveSupplierAction refuses, surfaced as a toast, not a 500.
    Livewire::actingAs($clerk)->test(SuppliersIndex::class, ['school' => $f['school']])
        ->call('approve', $supplier->id)->assertOk();

    expect($supplier->fresh()->status)->toBe('pending_approval');
});

it('applies withholding at the configured rate when a supplier has no valid tax clearance on the invoice date (AC-FIN-08-001)', function (): void {
    $f = procurementAdminFixture();
    $supplier = Supplier::factory()->for($f['school'])->create(['control_account_id' => $f['controlAccount']->id, 'is_vat_registered' => false]);
    $expenseAccount = Account::factory()->for($f['school'])->expense()->create(['code' => 'EXP1']);
    $clerk = procurementAdminUser($f, 'procurement.invoice.register');

    Livewire::actingAs($clerk)->test(Register::class, ['school' => $f['school']])
        ->set('supplierId', $supplier->id)
        ->set('invoiceNumber', 'INV-1001')
        ->set('lines.0.description', 'Stationery')
        ->set('lines.0.quantity', '1')
        ->set('lines.0.unit_price_minor', '500000')
        ->set('lines.0.expense_account_id', (string) $expenseAccount->id)
        ->call('register')
        ->assertOk();

    $invoice = SupplierInvoice::where('invoice_number', 'INV-1001')->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->withholding_applied)->toBeTrue()
        ->and($invoice->withholding_minor)->toBe(50000)
        ->and($invoice->net_payable_minor)->toBe(450000);
});

it('renders every procurement screen for a fully-permissioned user', function (): void {
    $f = procurementAdminFixture();
    $supplier = Supplier::factory()->for($f['school'])->create(['control_account_id' => $f['controlAccount']->id]);
    $admin = procurementAdminUser(
        $f,
        'procurement.supplier.view', 'procurement.supplier.manage', 'procurement.supplier.approve',
        'procurement.supplier.bank_change', 'procurement.requisition.create', 'procurement.requisition.approve',
        'procurement.quotation.manage', 'procurement.order.view', 'procurement.order.create',
        'procurement.order.approve', 'procurement.grn.create', 'procurement.invoice.register',
        'procurement.invoice.approve', 'procurement.payment.create', 'procurement.report.view',
    );

    Livewire::actingAs($admin)->test(SuppliersIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(SupplierShow::class, ['school' => $f['school'], 'supplier' => $supplier])->assertOk();
    Livewire::actingAs($admin)->test(BankChange::class, ['school' => $f['school'], 'supplier' => $supplier])->assertOk();
    Livewire::actingAs($admin)->test(Clearances::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(RequisitionsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Compare::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(OrdersIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(GrnCreate::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Register::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(MatchReview::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Run::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(ReportsIndex::class, ['school' => $f['school']])->assertOk();
});
