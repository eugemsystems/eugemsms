<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\Journal;
use Modules\People\Models\Department;
use Modules\Stores\Domain\Actions\ApprovePurchaseOrderAction;
use Modules\Stores\Domain\Actions\ApproveSupplierAction;
use Modules\Stores\Domain\Actions\ApproveSupplierInvoiceAction;
use Modules\Stores\Domain\Actions\AwardQuotationAction;
use Modules\Stores\Domain\Actions\BlacklistSupplierAction;
use Modules\Stores\Domain\Actions\ChangeSupplierBankDetailsAction;
use Modules\Stores\Domain\Actions\CheckContractExpiryAction;
use Modules\Stores\Domain\Actions\CheckTaxClearanceExpiryAction;
use Modules\Stores\Domain\Actions\ComputeSupplierAgingAction;
use Modules\Stores\Domain\Actions\CreatePurchaseOrderAction;
use Modules\Stores\Domain\Actions\CreateQuotationRequestAction;
use Modules\Stores\Domain\Actions\CreateSupplierAction;
use Modules\Stores\Domain\Actions\RecordGoodsReceivedNoteAction;
use Modules\Stores\Domain\Actions\RecordQuotationAction;
use Modules\Stores\Domain\Actions\RecordSupplierPaymentAction;
use Modules\Stores\Domain\Actions\RecordTaxClearanceAction;
use Modules\Stores\Domain\Actions\RegisterSupplierInvoiceAction;
use Modules\Stores\Domain\DataObjects\ChangeSupplierBankDetailsData;
use Modules\Stores\Domain\DataObjects\CreatePurchaseOrderData;
use Modules\Stores\Domain\DataObjects\CreateQuotationRequestData;
use Modules\Stores\Domain\DataObjects\CreateSupplierData;
use Modules\Stores\Domain\DataObjects\RecordGoodsReceivedNoteData;
use Modules\Stores\Domain\DataObjects\RecordQuotationData;
use Modules\Stores\Domain\DataObjects\RecordSupplierPaymentData;
use Modules\Stores\Domain\DataObjects\RecordTaxClearanceData;
use Modules\Stores\Domain\DataObjects\RegisterSupplierInvoiceData;
use Modules\Stores\Domain\Events\ContractExpiring;
use Modules\Stores\Domain\Events\DuplicateInvoiceSuspected;
use Modules\Stores\Domain\Events\MatchVarianceDetected;
use Modules\Stores\Domain\Events\NonFiscalInvoiceRegistered;
use Modules\Stores\Domain\Events\PurchaseOrderApproved;
use Modules\Stores\Domain\Events\SupplierApproved;
use Modules\Stores\Domain\Events\SupplierBankDetailsChanged;
use Modules\Stores\Domain\Events\TaxClearanceExpiring;
use Modules\Stores\Domain\Events\WithholdingApplied;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\PurchaseRequisition;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierContract;
use Modules\Stores\Models\SupplierInvoice;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, user2: User, department: Department, costCentre: CostCentre, grnAccrual: Account, bankGlAccount: Account, bankAccount: BankAccount}
 */
function fin08Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open']);
    $user = User::factory()->create();
    $user2 = User::factory()->create();

    foreach (['journal', 'purchase_requisition', 'quotation_request', 'purchase_order', 'goods_received_note', 'supplier_payment'] as $type) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $type, pattern: strtoupper(substr($type, 0, 3)).'/{SEQ:6}',
        ));
    }

    $department = Department::factory()->for($school)->create();
    $costCentre = CostCentre::factory()->for($school)->create();
    $grnAccrual = Account::factory()->for($school)->create(['code' => 'GRNACC']);
    $bankGlAccount = Account::factory()->for($school)->create(['code' => 'BANKGL']);
    $bankAccount = BankAccount::factory()->create(['school_id' => $school->id, 'gl_account_id' => $bankGlAccount->id]);

    return compact('school', 'year', 'term', 'user', 'user2', 'department', 'costCentre', 'grnAccrual', 'bankGlAccount', 'bankAccount');
}

/**
 * @param  array<string, mixed>  $f
 */
function fin08Supplier(array $f, bool $vatRegistered = true): Supplier
{
    $control = Account::factory()->for($f['school'])->controlAccount('supplier')->create();

    $supplier = app(CreateSupplierAction::class)->execute(new CreateSupplierData(
        schoolId: $f['school']->id, code: 'SUP-'.fake()->unique()->numberBetween(1000, 9999), name: 'Bhundu Stationers',
        supplierType: 'company', preferredCurrency: 'USD', createdByUserId: $f['user']->id,
        isVatRegistered: $vatRegistered, vatNumber: $vatRegistered ? 'VAT-12345' : null, controlAccountId: $control->id,
    ));

    return app(ApproveSupplierAction::class)->execute($supplier->id, $f['user2']->id);
}

it('requires approval by a different user than the creator before a supplier can trade (BR-FIN-08-001)', function (): void {
    Event::fake([SupplierApproved::class]);
    $f = fin08Fixture();
    $control = Account::factory()->for($f['school'])->controlAccount('supplier')->create();

    $supplier = app(CreateSupplierAction::class)->execute(new CreateSupplierData(
        schoolId: $f['school']->id, code: 'SUP-1', name: 'New Supplier', supplierType: 'company',
        preferredCurrency: 'USD', createdByUserId: $f['user']->id, controlAccountId: $control->id,
    ));

    expect($supplier->status)->toBe('pending_approval');

    expect(fn () => app(ApproveSupplierAction::class)->execute($supplier->id, $f['user']->id))
        ->toThrow(InvalidStateTransitionException::class);

    $approved = app(ApproveSupplierAction::class)->execute($supplier->id, $f['user2']->id);
    expect($approved->status)->toBe('active')
        ->and($approved->canReceiveOrders())->toBeTrue();
    Event::assertDispatched(SupplierApproved::class);
});

it('requires a different approver for a supplier bank detail change, and encrypts the account number at rest (BR-FIN-08-002/AC-FIN-08-009)', function (): void {
    Event::fake([SupplierBankDetailsChanged::class]);
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);

    expect(fn () => app(ChangeSupplierBankDetailsAction::class)->execute(new ChangeSupplierBankDetailsData(
        supplierId: $supplier->id, bankName: 'CBZ', bankBranch: 'Borrowdale', accountNumber: '1234567890',
        accountName: 'Bhundu Stationers', requestedByUserId: $f['user']->id, approvedByUserId: $f['user']->id,
    )))->toThrow(InvalidStateTransitionException::class);

    $updated = app(ChangeSupplierBankDetailsAction::class)->execute(new ChangeSupplierBankDetailsData(
        supplierId: $supplier->id, bankName: 'CBZ', bankBranch: 'Borrowdale', accountNumber: '1234567890',
        accountName: 'Bhundu Stationers', requestedByUserId: $f['user']->id, approvedByUserId: $f['user2']->id,
    ));

    expect($updated->account_number)->toBe('1234567890');

    $raw = DB::table('suppliers')->where('id', $supplier->id)->value('account_number');
    expect($raw)->not->toBe('1234567890');
    Event::assertDispatched(SupplierBankDetailsChanged::class);
});

it('refuses a new purchase order against a blacklisted supplier (BR-FIN-08-026/AC-FIN-08-012)', function (): void {
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);
    app(BlacklistSupplierAction::class)->execute($supplier->id, 'Repeated late and short deliveries.');

    expect(fn () => app(CreatePurchaseOrderAction::class)->execute(new CreatePurchaseOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        supplierId: $supplier->id, costCentreId: $f['costCentre']->id, orderDate: now(), currency: 'USD',
        lines: [['itemId' => null, 'description' => 'Stationery', 'quantityOrdered' => 1, 'unit' => 'ea', 'unitPriceMinor' => 1000, 'taxRatePercent' => 0, 'taxCategory' => 'exempt', 'expenseAccountId' => $f['grnAccrual']->id, 'isCapital' => false, 'storeId' => null]],
        createdByUserId: $f['user']->id,
    )))->toThrow(ValidationException::class);
});

it('commits budget on purchase order approval and fires PurchaseOrderApproved for FIN-11 to consume later (BR-FIN-08-010/AC-FIN-08-004)', function (): void {
    Event::fake([PurchaseOrderApproved::class]);
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);

    $order = app(CreatePurchaseOrderAction::class)->execute(new CreatePurchaseOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        supplierId: $supplier->id, costCentreId: $f['costCentre']->id, orderDate: now(), currency: 'USD',
        lines: [['itemId' => null, 'description' => 'Consulting service', 'quantityOrdered' => 1, 'unit' => 'ea', 'unitPriceMinor' => 800000, 'taxRatePercent' => 0, 'taxCategory' => 'exempt', 'expenseAccountId' => $f['grnAccrual']->id, 'isCapital' => false, 'storeId' => null]],
        createdByUserId: $f['user']->id,
    ));

    expect($order->status)->toBe('pending_approval')
        ->and($order->committed_minor)->toBe(0);

    $approved = app(ApprovePurchaseOrderAction::class)->execute($order->id, $f['user2']->id);

    expect($approved->status)->toBe('approved')
        ->and($approved->committed_minor)->toBe(800000);
    Event::assertDispatched(PurchaseOrderApproved::class);
});

it('posts Dr Inventory / Cr GRN Accrual and creates a real FIN-09 stock lot in the same transaction (BR-FIN-08-012/AC-FIN-08-006)', function (): void {
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);
    $store = Store::factory()->for($f['school'])->create();
    $item = InventoryItem::factory()->for($f['school'])->create();

    $order = app(CreatePurchaseOrderAction::class)->execute(new CreatePurchaseOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        supplierId: $supplier->id, costCentreId: $f['costCentre']->id, orderDate: now(), currency: 'USD',
        lines: [['itemId' => $item->id, 'description' => 'Maize meal', 'quantityOrdered' => 50, 'unit' => 'kg', 'unitPriceMinor' => 100, 'taxRatePercent' => 0, 'taxCategory' => 'exempt', 'expenseAccountId' => null, 'isCapital' => false, 'storeId' => $store->id]],
        createdByUserId: $f['user']->id,
    ));
    app(ApprovePurchaseOrderAction::class)->execute($order->id, $f['user2']->id);
    $poLine = $order->lines->first();

    $grn = app(RecordGoodsReceivedNoteAction::class)->execute(new RecordGoodsReceivedNoteData(
        schoolId: $f['school']->id, termId: $f['term']->id, purchaseOrderId: $order->id,
        receivedOn: now(), receivedByUserId: $f['user']->id, grnAccrualAccountId: $f['grnAccrual']->id,
        lines: [['poLineId' => $poLine->id, 'quantityDelivered' => 50, 'quantityAccepted' => 50, 'quantityRejected' => 0, 'rejectionReason' => null, 'batchNumber' => null, 'expiryDate' => null, 'unitCostMinor' => 100]],
    ));

    expect($grn->status)->toBe('posted')
        ->and($grn->total_value_minor)->toBe(5000);

    $journal = Journal::find($grn->journal_id);
    expect($journal->lines)->toHaveCount(2)
        ->and($journal->lines->firstWhere('direction', 'DR')->account_id)->toBe($store->inventory_account_id)
        ->and($journal->lines->firstWhere('direction', 'CR')->account_id)->toBe($f['grnAccrual']->id);

    $lot = StockLot::where('store_id', $store->id)->where('item_id', $item->id)->first();
    expect($lot)->not->toBeNull()
        ->and((float) $lot->quantity_remaining)->toBe(50.0)
        ->and($order->fresh()->status)->toBe('received');
});

it('reports a quantity and price variance beyond tolerance and requires approval (BR-FIN-08-016/AC-FIN-08-005)', function (): void {
    Event::fake([MatchVarianceDetected::class]);
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);
    $store = Store::factory()->for($f['school'])->create();
    $item = InventoryItem::factory()->for($f['school'])->create();

    $order = app(CreatePurchaseOrderAction::class)->execute(new CreatePurchaseOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        supplierId: $supplier->id, costCentreId: $f['costCentre']->id, orderDate: now(), currency: 'USD',
        lines: [['itemId' => $item->id, 'description' => 'Widgets', 'quantityOrdered' => 100, 'unit' => 'ea', 'unitPriceMinor' => 400, 'taxRatePercent' => 0, 'taxCategory' => 'exempt', 'expenseAccountId' => null, 'isCapital' => false, 'storeId' => $store->id]],
        createdByUserId: $f['user']->id,
    ));
    app(ApprovePurchaseOrderAction::class)->execute($order->id, $f['user2']->id);
    $poLine = $order->lines->first();

    app(RecordGoodsReceivedNoteAction::class)->execute(new RecordGoodsReceivedNoteData(
        schoolId: $f['school']->id, termId: $f['term']->id, purchaseOrderId: $order->id,
        receivedOn: now(), receivedByUserId: $f['user']->id, grnAccrualAccountId: $f['grnAccrual']->id,
        lines: [['poLineId' => $poLine->id, 'quantityDelivered' => 98, 'quantityAccepted' => 98, 'quantityRejected' => 0, 'rejectionReason' => null, 'batchNumber' => null, 'expiryDate' => null, 'unitCostMinor' => 400]],
    ));

    $invoice = app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'SUPINV-1', invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [['poLineId' => $poLine->id, 'grnLineId' => null, 'description' => 'Widgets', 'quantity' => 100, 'unitPriceMinor' => 415, 'taxCategory' => 'exempt', 'taxRatePercent' => 0, 'expenseAccountId' => $f['grnAccrual']->id, 'costCentreId' => null]],
        registeredByUserId: $f['user']->id, isFiscalInvoice: true,
    ));

    expect($invoice->match_status)->toBe('variance')
        ->and($invoice->match_variance_minor)->toBe(1500);
    Event::assertDispatched(MatchVarianceDetected::class);
});

it('assesses withholding on the invoice date and posts it only when there is no valid clearance that day (BR-FIN-08-003/AC-FIN-08-001/002)', function (): void {
    Event::fake([WithholdingApplied::class]);
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);

    $noClearanceInvoice = app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'INV-A', invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [['poLineId' => null, 'grnLineId' => null, 'description' => 'Service', 'quantity' => 1, 'unitPriceMinor' => 500000, 'taxCategory' => 'exempt', 'taxRatePercent' => 0, 'expenseAccountId' => $f['grnAccrual']->id, 'costCentreId' => null]],
        registeredByUserId: $f['user']->id,
    ));

    expect($noClearanceInvoice->withholding_applied)->toBeTrue()
        ->and($noClearanceInvoice->withholding_minor)->toBe(50000)
        ->and($noClearanceInvoice->net_payable_minor)->toBe(450000);
    Event::assertDispatched(WithholdingApplied::class);

    app(RecordTaxClearanceAction::class)->execute(new RecordTaxClearanceData(
        schoolId: $f['school']->id, supplierId: $supplier->id, certificateNumber: 'ITF-1',
        issuedOn: now()->subMonth(), expiresOn: now()->addMonths(6),
    ));

    $clearedInvoice = app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'INV-B', invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [['poLineId' => null, 'grnLineId' => null, 'description' => 'Service', 'quantity' => 1, 'unitPriceMinor' => 500000, 'taxCategory' => 'exempt', 'taxRatePercent' => 0, 'expenseAccountId' => $f['grnAccrual']->id, 'costCentreId' => null]],
        registeredByUserId: $f['user']->id,
    ));

    expect($clearedInvoice->withholding_applied)->toBeFalse()
        ->and($clearedInvoice->net_payable_minor)->toBe(500000);
});

it('warns that input VAT is unclaimable on a non-fiscal invoice from a VAT-registered supplier (BR-FIN-08-005/AC-FIN-08-003)', function (): void {
    Event::fake([NonFiscalInvoiceRegistered::class]);
    $f = fin08Fixture();
    $supplier = fin08Supplier($f, vatRegistered: true);

    $invoice = app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'INV-VAT-1', invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [['poLineId' => null, 'grnLineId' => null, 'description' => 'Goods', 'quantity' => 1, 'unitPriceMinor' => 200000, 'taxCategory' => 'standard', 'taxRatePercent' => 15, 'expenseAccountId' => $f['grnAccrual']->id, 'costCentreId' => null]],
        registeredByUserId: $f['user']->id, isFiscalInvoice: false,
    ));

    expect($invoice->input_vat_claimable)->toBeFalse();
    Event::assertDispatched(NonFiscalInvoiceRegistered::class, function (NonFiscalInvoiceRegistered $event) use ($invoice): bool {
        return $event->invoice->id === $invoice->id && $event->unclaimableVatMinor === 30000;
    });
});

it('blocks re-registering the same invoice number for a supplier, and warns on a near-duplicate amount/date (BR-FIN-08-018/AC-FIN-08-010)', function (): void {
    Event::fake([DuplicateInvoiceSuspected::class]);
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);
    $line = ['poLineId' => null, 'grnLineId' => null, 'description' => 'Service', 'quantity' => 1, 'unitPriceMinor' => 10000, 'taxCategory' => 'exempt', 'taxRatePercent' => 0, 'expenseAccountId' => $f['grnAccrual']->id, 'costCentreId' => null];

    app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'DUP-1', invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [$line], registeredByUserId: $f['user']->id,
    ));

    // The exact same number for the same supplier can never be
    // registered again — supplier_invoices enforces this at the DB
    // level, and no "acknowledge" flag can bypass a unique constraint.
    expect(fn () => app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'DUP-1', invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [$line], registeredByUserId: $f['user']->id,
    )))->toThrow(ValidationException::class);

    // A different number, same supplier/amount/date — warned, not blocked.
    $nearDuplicate = app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'DUP-2', invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [$line], registeredByUserId: $f['user']->id,
    ));

    expect($nearDuplicate)->not->toBeNull();
    Event::assertDispatched(DuplicateInvoiceSuspected::class);
});

it('refuses to approve an unmatched non-service invoice with no goods received note (BR-FIN-08-017)', function (): void {
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);
    $store = Store::factory()->for($f['school'])->create();
    $item = InventoryItem::factory()->for($f['school'])->create();

    $order = app(CreatePurchaseOrderAction::class)->execute(new CreatePurchaseOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        supplierId: $supplier->id, costCentreId: $f['costCentre']->id, orderDate: now(), currency: 'USD',
        lines: [['itemId' => $item->id, 'description' => 'Widgets', 'quantityOrdered' => 10, 'unit' => 'ea', 'unitPriceMinor' => 1000, 'taxRatePercent' => 0, 'taxCategory' => 'exempt', 'expenseAccountId' => null, 'isCapital' => false, 'storeId' => $store->id]],
        createdByUserId: $f['user']->id,
    ));
    app(ApprovePurchaseOrderAction::class)->execute($order->id, $f['user2']->id);
    $poLine = $order->lines->first();

    $invoice = app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'NO-GRN-1', invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [['poLineId' => $poLine->id, 'grnLineId' => null, 'description' => 'Widgets', 'quantity' => 10, 'unitPriceMinor' => 1000, 'taxCategory' => 'exempt', 'taxRatePercent' => 0, 'expenseAccountId' => $f['grnAccrual']->id, 'costCentreId' => null]],
        registeredByUserId: $f['user']->id,
    ));

    expect($invoice->match_status)->toBe('unmatched');

    expect(fn () => app(ApproveSupplierInvoiceAction::class)->execute($invoice->id, $f['user2']->id, $f['grnAccrual']->id))
        ->toThrow(InvalidStateTransitionException::class);
});

it('posts Dr GRN Accrual / Cr Creditors on invoice approval, clearing the accrual (BR-FIN-08-015/AC-FIN-08-007)', function (): void {
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);

    $invoice = app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'SVC-1', invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [['poLineId' => null, 'grnLineId' => null, 'description' => 'Service', 'quantity' => 1, 'unitPriceMinor' => 20000, 'taxCategory' => 'exempt', 'taxRatePercent' => 0, 'expenseAccountId' => $f['grnAccrual']->id, 'costCentreId' => null]],
        registeredByUserId: $f['user']->id,
    ));

    $approved = app(ApproveSupplierInvoiceAction::class)->execute($invoice->id, $f['user2']->id, $f['grnAccrual']->id);

    expect($approved->status)->toBe('approved');
    $journal = Journal::find($approved->journal_id);
    expect($journal->lines->firstWhere('direction', 'DR')->account_id)->toBe($f['grnAccrual']->id)
        ->and($journal->lines->firstWhere('direction', 'CR')->account_id)->toBe($supplier->control_account_id);
});

it('refuses a payment approver who already approved the invoice being paid (BR-FIN-08-020/AC-FIN-08-008)', function (): void {
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);
    app(RecordTaxClearanceAction::class)->execute(new RecordTaxClearanceData(
        schoolId: $f['school']->id, supplierId: $supplier->id, certificateNumber: 'ITF-PAY-1',
        issuedOn: now()->subMonth(), expiresOn: now()->addMonths(6),
    ));

    $invoice = app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'PAY-1', invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [['poLineId' => null, 'grnLineId' => null, 'description' => 'Service', 'quantity' => 1, 'unitPriceMinor' => 20000, 'taxCategory' => 'exempt', 'taxRatePercent' => 0, 'expenseAccountId' => $f['grnAccrual']->id, 'costCentreId' => null]],
        registeredByUserId: $f['user']->id,
    ));
    $approved = app(ApproveSupplierInvoiceAction::class)->execute($invoice->id, $f['user2']->id, $f['grnAccrual']->id);

    expect(fn () => app(RecordSupplierPaymentAction::class)->execute(new RecordSupplierPaymentData(
        schoolId: $f['school']->id, termId: $f['term']->id, supplierId: $supplier->id, invoiceIds: [$approved->id],
        paymentDate: now(), paymentMethod: 'bank_transfer', bankAccountId: $f['bankAccount']->id, approvedByUserId: $f['user2']->id,
    )))->toThrow(InvalidStateTransitionException::class);

    $paidBy = User::factory()->create();
    $payment = app(RecordSupplierPaymentAction::class)->execute(new RecordSupplierPaymentData(
        schoolId: $f['school']->id, termId: $f['term']->id, supplierId: $supplier->id, invoiceIds: [$approved->id],
        paymentDate: now(), paymentMethod: 'bank_transfer', bankAccountId: $f['bankAccount']->id, approvedByUserId: $paidBy->id,
    ));

    expect($payment->status)->toBe('approved')
        ->and($payment->net_minor)->toBe(20000)
        ->and($approved->fresh()->status)->toBe('paid')
        ->and($approved->fresh()->balance_minor)->toBe(0);

    $journal = Journal::find($payment->journal_id);
    expect($journal->lines)->toHaveCount(2)
        ->and($journal->lines->firstWhere('direction', 'CR')->account_id)->toBe($f['bankGlAccount']->id);
});

it('splits gross, net and withholding across three journal lines when paying a withheld invoice (§3)', function (): void {
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);
    $withholdingPayable = Account::factory()->for($f['school'])->create(['code' => 'WHTPAY']);

    $invoice = app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'WHT-1', invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [['poLineId' => null, 'grnLineId' => null, 'description' => 'Service', 'quantity' => 1, 'unitPriceMinor' => 500000, 'taxCategory' => 'exempt', 'taxRatePercent' => 0, 'expenseAccountId' => $f['grnAccrual']->id, 'costCentreId' => null]],
        registeredByUserId: $f['user']->id,
    ));
    $approved = app(ApproveSupplierInvoiceAction::class)->execute($invoice->id, $f['user2']->id, $f['grnAccrual']->id);
    expect($approved->withholding_minor)->toBe(50000);

    $paidBy = User::factory()->create();

    expect(fn () => app(RecordSupplierPaymentAction::class)->execute(new RecordSupplierPaymentData(
        schoolId: $f['school']->id, termId: $f['term']->id, supplierId: $supplier->id, invoiceIds: [$approved->id],
        paymentDate: now(), paymentMethod: 'bank_transfer', bankAccountId: $f['bankAccount']->id, approvedByUserId: $paidBy->id,
    )))->toThrow(ValidationException::class);

    $payment = app(RecordSupplierPaymentAction::class)->execute(new RecordSupplierPaymentData(
        schoolId: $f['school']->id, termId: $f['term']->id, supplierId: $supplier->id, invoiceIds: [$approved->id],
        paymentDate: now(), paymentMethod: 'bank_transfer', bankAccountId: $f['bankAccount']->id, approvedByUserId: $paidBy->id,
        withholdingPayableAccountId: $withholdingPayable->id,
    ));

    expect($payment->gross_minor)->toBe(500000)
        ->and($payment->withholding_minor)->toBe(50000)
        ->and($payment->net_minor)->toBe(450000);

    $journal = Journal::find($payment->journal_id);
    expect($journal->lines)->toHaveCount(3)
        ->and($journal->lines->firstWhere('account_id', $withholdingPayable->id)->direction)->toBe('CR');
});

it('awarding other than the lowest compliant quotation requires a written justification (BR-FIN-08-008/AC-FIN-08-011)', function (): void {
    $f = fin08Fixture();
    $cheapSupplier = fin08Supplier($f);
    $expensiveSupplier = fin08Supplier($f);

    $requisition = PurchaseRequisition::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'department_id' => $f['department']->id, 'cost_centre_id' => $f['costCentre']->id, 'requested_by' => $f['user']->id,
        'estimated_total_minor' => 50000,
    ]);

    $rfq = app(CreateQuotationRequestAction::class)->execute(new CreateQuotationRequestData(
        schoolId: $f['school']->id, requisitionId: $requisition->id, supplierIds: [$cheapSupplier->id, $expensiveSupplier->id],
        issuedOn: now(), closesOn: now()->addDays(3), requestedByUserId: $f['user']->id,
    ));

    $cheap = app(RecordQuotationAction::class)->execute(new RecordQuotationData(
        schoolId: $f['school']->id, quotationRequestId: $rfq->id, supplierId: $cheapSupplier->id, receivedOn: now(),
        subtotalMinor: 100000, taxMinor: 0, totalMinor: 100000, currency: 'USD',
        lines: [['description' => 'Goods', 'quantity' => 1, 'unitPriceMinor' => 100000, 'requisitionLineId' => null, 'leadTimeDays' => 5]],
    ));
    $expensive = app(RecordQuotationAction::class)->execute(new RecordQuotationData(
        schoolId: $f['school']->id, quotationRequestId: $rfq->id, supplierId: $expensiveSupplier->id, receivedOn: now(),
        subtotalMinor: 150000, taxMinor: 0, totalMinor: 150000, currency: 'USD',
        lines: [['description' => 'Goods', 'quantity' => 1, 'unitPriceMinor' => 150000, 'requisitionLineId' => null, 'leadTimeDays' => 1]],
    ));

    expect(fn () => app(AwardQuotationAction::class)->execute($rfq->id, $expensive->id, $f['user']->id))
        ->toThrow(ValidationException::class);

    $awarded = app(AwardQuotationAction::class)->execute($rfq->id, $expensive->id, $f['user']->id, 'Faster delivery critical for term start.');
    expect($awarded->awarded_quotation_id)->toBe($expensive->id)
        ->and($cheap->fresh()->status)->toBe('rejected');

    $awardedNoJustification = app(AwardQuotationAction::class)->execute($rfq->id, $cheap->id, $f['user']->id);
    // Re-awarding the lowest bid needs no justification at all.
    expect($awardedNoJustification->award_justification)->toBeNull();
});

it('computes supplier aging per currency from invoice due dates (BR-FIN-08-023)', function (): void {
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);

    SupplierInvoice::factory()->create([
        'school_id' => $f['school']->id, 'supplier_id' => $supplier->id, 'due_date' => now()->subDays(45),
        'balance_minor' => 20000, 'currency' => 'USD',
    ]);
    SupplierInvoice::factory()->create([
        'school_id' => $f['school']->id, 'supplier_id' => $supplier->id, 'due_date' => now()->addDays(10),
        'balance_minor' => 30000, 'currency' => 'USD',
    ]);

    $aging = app(ComputeSupplierAgingAction::class)->execute($supplier->id);

    expect($aging['USD']['days_31_60'])->toBe(20000)
        ->and($aging['USD']['current'])->toBe(30000)
        ->and($aging['USD']['total'])->toBe(50000);
});

it('fires ContractExpiring exactly on the renewal notice day, even for an auto-renewing contract (BR-FIN-08-024)', function (): void {
    Event::fake([ContractExpiring::class]);
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);

    SupplierContract::factory()->create([
        'school_id' => $f['school']->id, 'supplier_id' => $supplier->id, 'ends_on' => now()->addDays(30),
        'renewal_notice_days' => 30, 'auto_renew' => true, 'status' => 'active',
    ]);

    $expiring = app(CheckContractExpiryAction::class)->execute($f['school']->id);

    expect($expiring)->toHaveCount(1);
    Event::assertDispatched(ContractExpiring::class);
});

it('fires TaxClearanceExpiring on a configured alert day (BR-FIN-08-004)', function (): void {
    Event::fake([TaxClearanceExpiring::class]);
    $f = fin08Fixture();
    $supplier = fin08Supplier($f);

    app(RecordTaxClearanceAction::class)->execute(new RecordTaxClearanceData(
        schoolId: $f['school']->id, supplierId: $supplier->id, certificateNumber: 'ITF-EXP-1',
        issuedOn: now()->subMonths(11), expiresOn: now()->addDays(7),
    ));

    $expiring = app(CheckTaxClearanceExpiryAction::class)->execute($f['school']->id);

    expect($expiring)->toHaveCount(1);
    Event::assertDispatched(TaxClearanceExpiring::class);
});
