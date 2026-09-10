<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Department;
use Modules\Stores\Domain\Actions\ApprovePurchaseOrderAction;
use Modules\Stores\Domain\Actions\ApproveSupplierAction;
use Modules\Stores\Domain\Actions\ApproveSupplierInvoiceAction;
use Modules\Stores\Domain\Actions\ApproveVirementAction;
use Modules\Stores\Domain\Actions\CancelPurchaseOrderAction;
use Modules\Stores\Domain\Actions\CheckBudgetVarianceAction;
use Modules\Stores\Domain\Actions\ClosePurchaseOrderShortAction;
use Modules\Stores\Domain\Actions\CreateBudgetAction;
use Modules\Stores\Domain\Actions\CreateForecastAction;
use Modules\Stores\Domain\Actions\CreatePurchaseOrderAction;
use Modules\Stores\Domain\Actions\CreateSupplierAction;
use Modules\Stores\Domain\Actions\RecalculateBudgetLineActualsAction;
use Modules\Stores\Domain\Actions\RecordGoodsReceivedNoteAction;
use Modules\Stores\Domain\Actions\RegisterSupplierInvoiceAction;
use Modules\Stores\Domain\Actions\RequestPurchaseRequisitionAction;
use Modules\Stores\Domain\Actions\RequestVirementAction;
use Modules\Stores\Domain\Actions\ReviseBudgetAction;
use Modules\Stores\Domain\Actions\SubmitBudgetLineAction;
use Modules\Stores\Domain\DataObjects\CreateBudgetData;
use Modules\Stores\Domain\DataObjects\CreateForecastData;
use Modules\Stores\Domain\DataObjects\CreatePurchaseOrderData;
use Modules\Stores\Domain\DataObjects\CreateSupplierData;
use Modules\Stores\Domain\DataObjects\RecordGoodsReceivedNoteData;
use Modules\Stores\Domain\DataObjects\RegisterSupplierInvoiceData;
use Modules\Stores\Domain\DataObjects\RequestPurchaseRequisitionData;
use Modules\Stores\Domain\DataObjects\RequestVirementData;
use Modules\Stores\Domain\DataObjects\SubmitBudgetLineData;
use Modules\Stores\Domain\Events\BudgetExceeded;
use Modules\Stores\Domain\Events\BudgetRevised;
use Modules\Stores\Domain\Events\ForecastGenerated;
use Modules\Stores\Models\Budget;
use Modules\Stores\Models\BudgetLine;
use Modules\Stores\Models\Supplier;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, user2: User, department: Department, costCentre: CostCentre, expenseAccount: Account, grnAccrual: Account}
 */
function fin11Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open']);
    $user = User::factory()->create();
    $user2 = User::factory()->create();

    foreach (['journal', 'purchase_requisition', 'purchase_order', 'goods_received_note'] as $type) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $type, pattern: strtoupper(substr($type, 0, 3)).'/{SEQ:6}',
        ));
    }

    $department = Department::factory()->for($school)->create();
    $costCentre = CostCentre::factory()->for($school)->create();
    $expenseAccount = Account::factory()->for($school)->expense()->create(['code' => 'KITCHEN-PROV']);
    $grnAccrual = Account::factory()->for($school)->create(['code' => 'GRNACC']);

    return compact('school', 'year', 'term', 'user', 'user2', 'department', 'costCentre', 'expenseAccount', 'grnAccrual');
}

/**
 * @param  array<string, mixed>  $f
 */
function fin11BudgetLine(array $f, int $annualMinor = 18000000): BudgetLine
{
    $budget = app(CreateBudgetAction::class)->execute(new CreateBudgetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Operating Budget',
        budgetType: 'operating', periodBasis: 'annual', currency: 'USD', preparedByUserId: $f['user']->id,
    ));

    return app(SubmitBudgetLineAction::class)->execute(new SubmitBudgetLineData(
        budgetId: $budget->id, accountId: $f['expenseAccount']->id, costCentreId: $f['costCentre']->id,
        annualAmountMinor: $annualMinor, basisNote: 'Prior year actual plus inflation.',
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function fin11Supplier(array $f): Supplier
{
    $control = Account::factory()->for($f['school'])->controlAccount('supplier')->create();

    $supplier = app(CreateSupplierAction::class)->execute(new CreateSupplierData(
        schoolId: $f['school']->id, code: 'SUP-'.fake()->unique()->numberBetween(1000, 9999), name: 'Kitchen Provisions Ltd',
        supplierType: 'company', preferredCurrency: 'USD', createdByUserId: $f['user']->id, controlAccountId: $control->id,
    ));

    return app(ApproveSupplierAction::class)->execute($supplier->id, $f['user2']->id);
}

it('walks the full commitment lifecycle: approval commits, matching releases proportionally, closing short releases the residual (AC-FIN-11-001/002/003)', function (): void {
    $f = fin11Fixture();
    $line = fin11BudgetLine($f);
    $supplier = fin11Supplier($f);

    $order = app(CreatePurchaseOrderAction::class)->execute(new CreatePurchaseOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        supplierId: $supplier->id, costCentreId: $f['costCentre']->id, orderDate: now(), currency: 'USD',
        lines: [['itemId' => null, 'description' => 'Maize meal', 'quantityOrdered' => 24, 'unit' => 'bag', 'unitPriceMinor' => 100000, 'taxRatePercent' => 0, 'taxCategory' => 'exempt', 'expenseAccountId' => $f['expenseAccount']->id, 'isCapital' => false, 'storeId' => null]],
        createdByUserId: $f['user']->id, budgetLineId: $line->id,
    ));

    // Nothing committed until approval.
    expect($line->fresh()->available_minor)->toBe(18000000);

    app(ApprovePurchaseOrderAction::class)->execute($order->id, $f['user2']->id);

    // AC-FIN-11-001: available drops immediately by the full 24,000.
    expect($line->fresh()->committed_minor)->toBe(2400000)
        ->and($line->fresh()->available_minor)->toBe(15600000);

    $poLine = $order->lines->first();

    // Short delivery: 23.4 of 24 bags, i.e. $23,400 of $24,000.
    $grn = app(RecordGoodsReceivedNoteAction::class)->execute(new RecordGoodsReceivedNoteData(
        schoolId: $f['school']->id, termId: $f['term']->id, purchaseOrderId: $order->id,
        receivedOn: now(), receivedByUserId: $f['user']->id, grnAccrualAccountId: $f['grnAccrual']->id,
        lines: [['poLineId' => $poLine->id, 'quantityDelivered' => 23.4, 'quantityAccepted' => 23.4, 'quantityRejected' => 0, 'rejectionReason' => null, 'batchNumber' => null, 'expiryDate' => null, 'unitCostMinor' => 100000]],
    ));
    expect($grn->total_value_minor)->toBe(2340000);

    $invoice = app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'KP-INV-1', purchaseOrderId: $order->id, invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30), currency: 'USD',
        lines: [['poLineId' => $poLine->id, 'grnLineId' => null, 'description' => 'Maize meal', 'quantity' => 23.4, 'unitPriceMinor' => 100000, 'taxCategory' => 'exempt', 'taxRatePercent' => 0, 'expenseAccountId' => $f['expenseAccount']->id, 'costCentreId' => $f['costCentre']->id]],
        registeredByUserId: $f['user']->id,
    ));
    expect($invoice->match_status)->toBe('matched');

    app(ApproveSupplierInvoiceAction::class)->execute($invoice->id, $f['user2']->id, $f['grnAccrual']->id);

    // AC-FIN-11-002: committed drops by 23,400, actual rises by 23,400,
    // available is unchanged at 156,000.
    $line->refresh();
    expect($line->committed_minor)->toBe(60000)
        ->and($line->actual_minor)->toBe(2340000)
        ->and($line->available_minor)->toBe(15600000);

    app(ClosePurchaseOrderShortAction::class)->execute($order->id, 'Supplier out of stock for the remainder.', $f['user']->id);

    // AC-FIN-11-003: the residual $600 commitment releases.
    $line->refresh();
    expect($line->committed_minor)->toBe(0)
        ->and($line->available_minor)->toBe(15660000);
});

it('cancelling a purchase order releases its full outstanding commitment (BR-FIN-11-006)', function (): void {
    $f = fin11Fixture();
    $line = fin11BudgetLine($f);
    $supplier = fin11Supplier($f);

    $order = app(CreatePurchaseOrderAction::class)->execute(new CreatePurchaseOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        supplierId: $supplier->id, costCentreId: $f['costCentre']->id, orderDate: now(), currency: 'USD',
        lines: [['itemId' => null, 'description' => 'Cleaning supplies', 'quantityOrdered' => 1, 'unit' => 'ea', 'unitPriceMinor' => 500000, 'taxRatePercent' => 0, 'taxCategory' => 'exempt', 'expenseAccountId' => $f['expenseAccount']->id, 'isCapital' => false, 'storeId' => null]],
        createdByUserId: $f['user']->id, budgetLineId: $line->id,
    ));
    app(ApprovePurchaseOrderAction::class)->execute($order->id, $f['user2']->id);
    expect($line->fresh()->committed_minor)->toBe(500000);

    app(CancelPurchaseOrderAction::class)->execute($order->id, 'Duplicate order raised in error.', $f['user']->id);

    expect($line->fresh()->committed_minor)->toBe(0)
        ->and($line->fresh()->available_minor)->toBe(18000000);
});

it('does not block a requisition that exceeds available budget, but records the overspend (BR-FIN-11-009/AC-FIN-11-004)', function (): void {
    $f = fin11Fixture();
    $line = fin11BudgetLine($f, annualMinor: 100000);

    $withinBudget = app(RequestPurchaseRequisitionAction::class)->execute(new RequestPurchaseRequisitionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        departmentId: $f['department']->id, costCentreId: $f['costCentre']->id, justification: 'Routine restock.',
        requestedByUserId: $f['user']->id, currency: 'USD',
        lines: [['itemId' => null, 'description' => 'Stationery', 'quantity' => 1, 'unit' => 'ea', 'estimatedUnitMinor' => 50000]],
        budgetLineId: $line->id,
    ));
    expect($withinBudget->budget_check_result)->toBe('within');

    $overspend = app(RequestPurchaseRequisitionAction::class)->execute(new RequestPurchaseRequisitionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        departmentId: $f['department']->id, costCentreId: $f['costCentre']->id, justification: 'Urgent replacement.',
        requestedByUserId: $f['user']->id, currency: 'USD',
        lines: [['itemId' => null, 'description' => 'Extra stationery', 'quantity' => 1, 'unit' => 'ea', 'estimatedUnitMinor' => 150000]],
        budgetLineId: $line->id,
    ));

    // Never blocked — the requisition still exists, just flagged.
    expect($overspend->budget_check_result)->toBe('exceeds')
        ->and($overspend->status)->toBe('pending')
        ->and($overspend->budget_available_minor)->toBe(100000);
});

it('never accepts a manually entered actual — it always derives from journal_lines (BR-FIN-11-008/AC-FIN-11-005)', function (): void {
    $f = fin11Fixture();
    $line = fin11BudgetLine($f);

    expect($line->actual_minor)->toBe(0);

    $recalculated = app(RecalculateBudgetLineActualsAction::class)->execute($line->id);

    // No journal activity yet against this account/cost centre.
    expect($recalculated->actual_minor)->toBe(0);
});

it('models a scenario without ever altering the approved budget (BR-FIN-11-015/AC-FIN-11-006)', function (): void {
    Event::fake([ForecastGenerated::class]);
    $f = fin11Fixture();
    $line = fin11BudgetLine($f);

    $forecast = app(CreateForecastAction::class)->execute(new CreateForecastData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, forecastType: 'fee_income',
        scenarioName: 'Collection 85%', assumptions: ['collection_rate' => 0.85],
        projections: ['term_1' => 900000, 'term_2' => 900000, 'term_3' => 900000],
        generatedByUserId: $f['user']->id, isBaseline: false,
    ));

    expect($forecast->scenario_name)->toBe('Collection 85%')
        ->and($forecast->is_baseline)->toBeFalse();
    // The budget line itself is completely untouched by a scenario.
    expect($line->fresh()->annual_amount_minor)->toBe(18000000);
    Event::assertDispatched(ForecastGenerated::class);
});

it('refuses virement in or out of a locked budget line (BR-FIN-11-011)', function (): void {
    $f = fin11Fixture();
    $fromLine = fin11BudgetLine($f);
    $budget = $fromLine->budget;
    $toLine = app(SubmitBudgetLineAction::class)->execute(new SubmitBudgetLineData(
        budgetId: $budget->id, accountId: Account::factory()->for($f['school'])->expense()->create()->id,
        costCentreId: $f['costCentre']->id, annualAmountMinor: 5000000,
    ));
    $toLine->update(['is_locked' => true]);

    expect(fn () => app(RequestVirementAction::class)->execute(new RequestVirementData(
        budgetId: $budget->id, fromLineId: $fromLine->id, toLineId: $toLine->id, amountMinor: 100000,
        reason: 'Reallocating underspend.', requestedByUserId: $f['user']->id, effectiveFrom: now(),
    )))->toThrow(ValidationException::class);

    $toLine->update(['is_locked' => false]);
    $virement = app(RequestVirementAction::class)->execute(new RequestVirementData(
        budgetId: $budget->id, fromLineId: $fromLine->id, toLineId: $toLine->id, amountMinor: 100000,
        reason: 'Reallocating underspend.', requestedByUserId: $f['user']->id, effectiveFrom: now(),
    ));

    $approved = app(ApproveVirementAction::class)->execute($virement->id, $f['user2']->id);
    expect($approved->status)->toBe('approved')
        ->and($fromLine->fresh()->annual_amount_minor)->toBe(17900000)
        ->and($toLine->fresh()->annual_amount_minor)->toBe(5100000);
});

it('alerts when a budget line moves beyond its configured variance tolerance (BR-FIN-11-017/AC-FIN-11-007)', function (): void {
    Event::fake([BudgetExceeded::class]);
    $f = fin11Fixture();
    $line = fin11BudgetLine($f, annualMinor: 100000);
    $line->update(['committed_minor' => 120000]);
    $line->recomputeAvailable();
    $line->save();

    $exceeded = app(CheckBudgetVarianceAction::class)->execute($f['school']->id);

    expect($exceeded)->toHaveCount(1);
    Event::assertDispatched(BudgetExceeded::class);
});

it('supersedes the prior version on revision while keeping both retrievable (BR-FIN-11-002)', function (): void {
    Event::fake([BudgetRevised::class]);
    $f = fin11Fixture();
    $line = fin11BudgetLine($f);
    $budget = Budget::find($line->budget_id);
    $budget->update(['status' => 'active']);

    $revision = app(ReviseBudgetAction::class)->execute($budget->id, $f['user']->id);

    expect($revision->version)->toBe(2)
        ->and($budget->fresh()->status)->toBe('revised')
        ->and(Budget::find($budget->id))->not->toBeNull()
        ->and(Budget::find($revision->id))->not->toBeNull();
    Event::assertDispatched(BudgetRevised::class);
});
