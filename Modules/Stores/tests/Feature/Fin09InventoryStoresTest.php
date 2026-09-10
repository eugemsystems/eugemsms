<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Boarding\Domain\Support\StoreIssuanceProvider;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Sessions\TransitionPeriodStateAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Sessions\TransitionPeriodData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Exceptions\PeriodLockedException;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CreateFeeComponentAction;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AdHocCharge;
use Modules\Finance\Models\Journal;
use Modules\People\Models\Student;
use Modules\Stores\Domain\Actions\ApproveStockTakeVarianceAction;
use Modules\Stores\Domain\Actions\ApproveStoreRequisitionAction;
use Modules\Stores\Domain\Actions\CheckExpiringLotsAction;
use Modules\Stores\Domain\Actions\CheckReorderLevelsAction;
use Modules\Stores\Domain\Actions\ComputeConsumptionBaselineAction;
use Modules\Stores\Domain\Actions\CreateStockTakeAction;
use Modules\Stores\Domain\Actions\DetectConsumptionAnomalyAction;
use Modules\Stores\Domain\Actions\DispatchStockTransferAction;
use Modules\Stores\Domain\Actions\GetBlindCountSheetAction;
use Modules\Stores\Domain\Actions\IssueSaleableItemToLearnerAction;
use Modules\Stores\Domain\Actions\IssueStockAction;
use Modules\Stores\Domain\Actions\ReceiveStockAction;
use Modules\Stores\Domain\Actions\ReceiveStockTransferAction;
use Modules\Stores\Domain\Actions\RecordAnomalyInvestigationAction;
use Modules\Stores\Domain\Actions\RecordRecountAction;
use Modules\Stores\Domain\Actions\RecordRequisitionReturnAction;
use Modules\Stores\Domain\Actions\RequestStoreRequisitionAction;
use Modules\Stores\Domain\Actions\SubmitStockCountAction;
use Modules\Stores\Domain\Actions\WriteOffExpiredStockAction;
use Modules\Stores\Domain\DataObjects\CreateStockTakeData;
use Modules\Stores\Domain\DataObjects\DispatchStockTransferData;
use Modules\Stores\Domain\DataObjects\IssueSaleableItemToLearnerData;
use Modules\Stores\Domain\DataObjects\ReceiveStockData;
use Modules\Stores\Domain\DataObjects\RequestStoreRequisitionData;
use Modules\Stores\Domain\Events\ConsumptionAnomalyDetected;
use Modules\Stores\Domain\Events\ExpiryApproaching;
use Modules\Stores\Domain\Events\ItemCapitalisationDue;
use Modules\Stores\Domain\Events\NegativeStockIssued;
use Modules\Stores\Domain\Events\ReorderLevelBreached;
use Modules\Stores\Domain\Events\TransferDiscrepancy;
use Modules\Stores\Domain\Exceptions\InsufficientStockException;
use Modules\Stores\Domain\Support\EloquentStoreIssuanceProvider;
use Modules\Stores\Models\ConsumptionAnomaly;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\StockTake;
use Modules\Stores\Models\StockTakeLine;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreItemSetting;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, store: Store, shrinkage: Account}
 */
function fin09Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open']);
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}',
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'store_requisition', pattern: 'REQ/{SEQ:6}', academicYearId: $year->id,
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'stock_transfer', pattern: 'TRF/{SEQ:6}', academicYearId: $year->id,
    ));

    $store = Store::factory()->for($school)->create();
    $shrinkage = Account::factory()->for($school)->expense()->create(['code' => 'SHRINK']);

    return compact('school', 'year', 'term', 'user', 'store', 'shrinkage');
}

/**
 * @param  array<string, mixed>  $f
 */
function fin09Receive(array $f, InventoryItem $item, float $quantity, int $unitCostMinor, ?string $expiryDate = null, ?Store $store = null): StockLot
{
    return app(ReceiveStockAction::class)->execute(new ReceiveStockData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: ($store ?? $f['store'])->id, itemId: $item->id, quantity: $quantity, unitCostMinor: $unitCostMinor,
        currency: 'USD', receivedOn: now(), performedByUserId: $f['user']->id,
        contraAccountId: $f['shrinkage']->id, expiryDate: $expiryDate !== null ? Carbon::parse($expiryDate) : null,
    ));
}

it('consumes FIFO across two lots and posts one journal for the aggregate cost (AC-FIN-09-001)', function (): void {
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create();

    $lotA = fin09Receive($f, $item, 10, 100); // $1.00/unit
    fin09Receive($f, $item, 10, 150); // $1.50/unit

    $requisition = app(RequestStoreRequisitionAction::class)->execute(new RequestStoreRequisitionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $f['store']->id, costCentreId: $f['store']->cost_centre_id, purpose: 'Kitchen use',
        requestedByUserId: $f['user']->id, currency: 'USD',
        lines: [['itemId' => $item->id, 'quantity' => 15, 'unit' => $item->base_unit]],
    ));
    app(ApproveStoreRequisitionAction::class)->execute($requisition->id, $f['user']->id);

    $issued = app(IssueStockAction::class)->execute($requisition->id, $f['user']->id);

    // 10 units @ 100 + 5 units @ 150 = 1750
    expect($issued->total_cost_minor)->toBe(1750);

    $journal = Journal::find($issued->journal_id);
    expect($journal->lines)->toHaveCount(2)
        ->and($journal->lines->firstWhere('direction', 'DR')->amount_minor)->toBe(1750)
        ->and($journal->lines->firstWhere('direction', 'CR')->account_id)->toBe($f['store']->inventory_account_id);

    expect($lotA->fresh()->is_depleted)->toBeTrue()
        ->and((float) $lotA->fresh()->quantity_remaining)->toBe(0.0);
});

it('aggregates every line onto one journal, grouped by account and cost centre (BR-FIN-09-007)', function (): void {
    $f = fin09Fixture();
    $itemA = InventoryItem::factory()->for($f['school'])->create();
    $itemB = InventoryItem::factory()->for($f['school'])->create();
    fin09Receive($f, $itemA, 20, 100);
    fin09Receive($f, $itemB, 20, 200);

    $requisition = app(RequestStoreRequisitionAction::class)->execute(new RequestStoreRequisitionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $f['store']->id, costCentreId: $f['store']->cost_centre_id, purpose: 'Kitchen use',
        requestedByUserId: $f['user']->id, currency: 'USD',
        lines: [
            ['itemId' => $itemA->id, 'quantity' => 5, 'unit' => $itemA->base_unit],
            ['itemId' => $itemB->id, 'quantity' => 5, 'unit' => $itemB->base_unit],
        ],
    ));
    app(ApproveStoreRequisitionAction::class)->execute($requisition->id, $f['user']->id);
    $issued = app(IssueStockAction::class)->execute($requisition->id, $f['user']->id);

    // Both items fall back to the store's own default expense account and
    // share the requisition's cost centre, so they collapse into one DR line.
    $journal = Journal::find($issued->journal_id);
    expect($journal->lines)->toHaveCount(2)
        ->and($journal->lines->firstWhere('direction', 'DR')->amount_minor)->toBe(500 + 1000);
});

it('reverses a return at the original issue cost, even after a new lot arrives at a different price (BR-FIN-09-008)', function (): void {
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create();
    fin09Receive($f, $item, 10, 100);

    $requisition = app(RequestStoreRequisitionAction::class)->execute(new RequestStoreRequisitionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $f['store']->id, costCentreId: $f['store']->cost_centre_id, purpose: 'Kitchen use',
        requestedByUserId: $f['user']->id, currency: 'USD',
        lines: [['itemId' => $item->id, 'quantity' => 10, 'unit' => $item->base_unit]],
    ));
    app(ApproveStoreRequisitionAction::class)->execute($requisition->id, $f['user']->id);
    app(IssueStockAction::class)->execute($requisition->id, $f['user']->id);

    // Price has since risen — the return must still reverse at the
    // original $1.00/unit, never at this new $5.00/unit.
    fin09Receive($f, $item, 10, 500);

    $line = app(RecordRequisitionReturnAction::class)->execute($requisition->id, $item->id, 4, $f['user']->id);

    $returnMovement = StockMovement::where('source_id', $requisition->id)->where('movement_type', 'return')->first();
    expect((int) $returnMovement->unit_cost_minor)->toBe(100)
        ->and((int) $returnMovement->total_cost_minor)->toBe(400)
        ->and((float) $line->fresh()->quantity_returned)->toBe(4.0);
});

it('refuses to issue against an expired lot, and expired stock is written off to a wastage account instead (BR-FIN-09-011)', function (): void {
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create(['is_perishable' => true]);
    $lot = fin09Receive($f, $item, 10, 100, now()->subDay()->toDateString());

    $requisition = app(RequestStoreRequisitionAction::class)->execute(new RequestStoreRequisitionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $f['store']->id, costCentreId: $f['store']->cost_centre_id, purpose: 'Kitchen use',
        requestedByUserId: $f['user']->id, currency: 'USD',
        lines: [['itemId' => $item->id, 'quantity' => 5, 'unit' => $item->base_unit]],
    ));
    app(ApproveStoreRequisitionAction::class)->execute($requisition->id, $f['user']->id);

    expect(fn () => app(IssueStockAction::class)->execute($requisition->id, $f['user']->id))
        ->toThrow(InsufficientStockException::class);

    $movement = app(WriteOffExpiredStockAction::class)->execute(
        lotId: $lot->id, quantity: 10, wastageAccountId: $f['shrinkage']->id, approvedByUserId: $f['user']->id,
        academicYearId: $f['year']->id, termId: $f['term']->id, reason: 'Past expiry, discarded per hygiene policy.',
    );

    expect($movement->movement_type)->toBe('write_off')
        ->and((float) $lot->fresh()->quantity_remaining)->toBe(0.0)
        ->and($lot->fresh()->is_depleted)->toBeTrue();
});

it('never exposes system_quantity on the blind count sheet (BR-FIN-09-014)', function (): void {
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create();
    StoreItemSetting::factory()->create(['school_id' => $f['school']->id, 'store_id' => $f['store']->id, 'item_id' => $item->id]);
    fin09Receive($f, $item, 25, 100);

    $take = app(CreateStockTakeAction::class)->execute(new CreateStockTakeData(
        schoolId: $f['school']->id, termId: $f['term']->id, storeId: $f['store']->id,
        takeType: 'full', scheduledFor: now(),
    ));

    $sheet = app(GetBlindCountSheetAction::class)->execute($take->id);

    expect($sheet)->toHaveCount(1);
    $line = $sheet->first();
    expect(property_exists($line, 'systemQuantity'))->toBeFalse()
        ->and($line->itemName)->toBe($item->name);
});

it('requires a recount to be performed by someone other than the original counter (BR-FIN-09-015)', function (): void {
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create();
    $take = StockTake::factory()->create(['school_id' => $f['school']->id, 'term_id' => $f['term']->id, 'store_id' => $f['store']->id]);
    $line = StockTakeLine::factory()->create([
        'school_id' => $f['school']->id, 'stock_take_id' => $take->id, 'item_id' => $item->id, 'system_quantity' => 100,
    ]);

    $submitted = app(SubmitStockCountAction::class)->execute($line->id, 80, $f['user']->id);
    expect($submitted->requires_recount)->toBeTrue();

    expect(fn () => app(RecordRecountAction::class)->execute($line->id, 80, $f['user']->id))
        ->toThrow(ValidationException::class);

    $otherUser = User::factory()->create();
    $recounted = app(RecordRecountAction::class)->execute($line->id, 95, $otherUser->id);
    expect($recounted->requires_recount)->toBeFalse()
        ->and((float) $recounted->variance_quantity)->toBe(-5.0);
});

it('posts one adjustment journal to the shrinkage account when a stock take variance is approved (BR-FIN-09-016)', function (): void {
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create();
    fin09Receive($f, $item, 100, 100);

    $take = StockTake::factory()->create(['school_id' => $f['school']->id, 'term_id' => $f['term']->id, 'store_id' => $f['store']->id]);
    $line = StockTakeLine::factory()->create([
        'school_id' => $f['school']->id, 'stock_take_id' => $take->id, 'item_id' => $item->id, 'system_quantity' => 100,
    ]);

    app(SubmitStockCountAction::class)->execute($line->id, 98, $f['user']->id, 100);
    $line->update(['variance_reason' => 'Two units damaged in storage, confirmed by custodian.']);

    $posted = app(ApproveStockTakeVarianceAction::class)->execute(
        stockTakeId: $take->id, approvedByUserId: $f['user']->id,
        shrinkageAccountId: $f['shrinkage']->id, academicYearId: $f['year']->id,
    );

    expect($posted->status)->toBe('posted')
        ->and($posted->journal_id)->not->toBeNull();

    $journal = Journal::find($posted->journal_id);
    expect($journal->lines)->toHaveCount(2)
        ->and($journal->lines->firstWhere('direction', 'DR')->account_id)->toBe($f['shrinkage']->id)
        ->and((float) StockMovement::where('source_id', $take->id)->where('movement_type', 'adjustment_down')->first()->quantity)->toBe(2.0);
});

it('refuses to approve stock take variance while a line still requires a recount (BR-FIN-09-015/016)', function (): void {
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create();
    $take = StockTake::factory()->create(['school_id' => $f['school']->id, 'term_id' => $f['term']->id, 'store_id' => $f['store']->id]);
    $line = StockTakeLine::factory()->create([
        'school_id' => $f['school']->id, 'stock_take_id' => $take->id, 'item_id' => $item->id, 'system_quantity' => 100,
    ]);
    app(SubmitStockCountAction::class)->execute($line->id, 50, $f['user']->id);

    app(ApproveStockTakeVarianceAction::class)->execute(
        stockTakeId: $take->id, approvedByUserId: $f['user']->id,
        shrinkageAccountId: $f['shrinkage']->id, academicYearId: $f['year']->id,
    );
})->throws(InvalidStateTransitionException::class);

it('dispatches and receives a transfer cleanly when the received quantity matches (BR-FIN-09-018)', function (): void {
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create();
    $toStore = Store::factory()->for($f['school'])->kitchen()->create();
    fin09Receive($f, $item, 20, 100);

    $transfer = app(DispatchStockTransferAction::class)->execute(new DispatchStockTransferData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        fromStoreId: $f['store']->id, toStoreId: $toStore->id, reason: 'Weekly resupply.',
        items: [['itemId' => $item->id, 'quantity' => 8]], dispatchedByUserId: $f['user']->id,
    ));

    expect($transfer->status)->toBe('in_transit');

    $received = app(ReceiveStockTransferAction::class)->execute(
        transferId: $transfer->id,
        receivedQuantitiesByLineId: [$transfer->lines->first()->id => 8],
        receivedByUserId: $f['user']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
    );

    expect($received->status)->toBe('received');

    $sourceOnHand = (float) StockMovement::where('store_id', $f['store']->id)->where('item_id', $item->id)->where('direction', 'in')->sum('quantity')
        - (float) StockMovement::where('store_id', $f['store']->id)->where('item_id', $item->id)->where('direction', 'out')->sum('quantity');
    $destOnHand = (float) StockMovement::where('store_id', $toStore->id)->where('item_id', $item->id)->where('direction', 'in')->sum('quantity');

    expect($sourceOnHand)->toBe(12.0)
        ->and($destOnHand)->toBe(8.0);
});

it('flags a discrepancy and blocks completion when the received quantity does not match what was dispatched (BR-FIN-09-019)', function (): void {
    Event::fake([TransferDiscrepancy::class]);
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create();
    $toStore = Store::factory()->for($f['school'])->kitchen()->create();
    fin09Receive($f, $item, 20, 100);

    $transfer = app(DispatchStockTransferAction::class)->execute(new DispatchStockTransferData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        fromStoreId: $f['store']->id, toStoreId: $toStore->id, reason: 'Weekly resupply.',
        items: [['itemId' => $item->id, 'quantity' => 10]], dispatchedByUserId: $f['user']->id,
    ));

    $received = app(ReceiveStockTransferAction::class)->execute(
        transferId: $transfer->id,
        receivedQuantitiesByLineId: [$transfer->lines->first()->id => 7],
        receivedByUserId: $f['user']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
    );

    expect($received->status)->toBe('discrepancy')
        ->and($received->discrepancy_note)->not->toBeNull();
    Event::assertDispatched(TransferDiscrepancy::class);
});

it('issues a saleable item to a learner, raising an ad hoc charge and posting cost of sales separately (BR-FIN-09-020)', function (): void {
    $f = fin09Fixture();
    $income = Account::factory()->for($f['school'])->income()->create();
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();
    $feeComponent = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $f['school']->id, code: 'UNIFORM', name: 'Uniform Sales', category: 'other',
        incomeAccountId: $income->id, debtorAccountId: $debtor->id, defaultCurrency: 'USD', createdByUserId: $f['user']->id,
    ));
    $item = InventoryItem::factory()->saleable()->for($f['school'])->create(['sale_fee_component_id' => $feeComponent->id]);
    $student = Student::factory()->for($f['school'])->create();
    fin09Receive($f, $item, 10, 800); // cost $8.00/unit, sells at $15.00 (factory default)

    $movement = app(IssueSaleableItemToLearnerAction::class)->execute(new IssueSaleableItemToLearnerData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $f['store']->id, itemId: $item->id, studentId: $student->id, quantity: 1,
        issuedByUserId: $f['user']->id, issuedAt: now(),
    ));

    $charge = AdHocCharge::where('student_id', $student->id)->first();
    expect($charge)->not->toBeNull()
        ->and($charge->amount_minor)->toBe(1500)
        ->and($movement->total_cost_minor)->toBe(800);

    $cogsJournal = Journal::find($movement->journal_id);
    expect($cogsJournal->journal_type)->toBe('STOCK_SALE_COGS')
        ->and($cogsJournal->lines->firstWhere('direction', 'DR')->amount_minor)->toBe(800);
});

it('fires ItemCapitalisationDue instead of fabricating a FIN-10 asset when a capitalisable item crosses its threshold (BR-FIN-09-021)', function (): void {
    Event::fake([ItemCapitalisationDue::class]);
    $f = fin09Fixture();
    $item = InventoryItem::factory()->capitalisable()->for($f['school'])->create();
    fin09Receive($f, $item, 5, 60000); // above the 50000 threshold

    $requisition = app(RequestStoreRequisitionAction::class)->execute(new RequestStoreRequisitionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $f['store']->id, costCentreId: $f['store']->cost_centre_id, purpose: 'Lab equipment issue',
        requestedByUserId: $f['user']->id, currency: 'USD',
        lines: [['itemId' => $item->id, 'quantity' => 1, 'unit' => $item->base_unit]],
    ));
    app(ApproveStoreRequisitionAction::class)->execute($requisition->id, $f['user']->id);
    $issued = app(IssueStockAction::class)->execute($requisition->id, $f['user']->id);

    expect($issued->total_cost_minor)->toBe(60000);
    Event::assertDispatched(ItemCapitalisationDue::class);
});

it('issues below zero on hand at last known cost and flags it, only when the store allows negative stock (BR-FIN-09-005)', function (): void {
    Event::fake([NegativeStockIssued::class]);
    $f = fin09Fixture();
    $negativeStore = Store::factory()->for($f['school'])->create(['code' => 'NEG', 'allows_negative_stock' => true]);
    $item = InventoryItem::factory()->for($f['school'])->create();
    fin09Receive($f, $item, 5, 100, store: $negativeStore);

    $requisition = app(RequestStoreRequisitionAction::class)->execute(new RequestStoreRequisitionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $negativeStore->id, costCentreId: $negativeStore->cost_centre_id, purpose: 'Overdraw test',
        requestedByUserId: $f['user']->id, currency: 'USD',
        lines: [['itemId' => $item->id, 'quantity' => 8, 'unit' => $item->base_unit]],
    ));
    app(ApproveStoreRequisitionAction::class)->execute($requisition->id, $f['user']->id);
    app(IssueStockAction::class)->execute($requisition->id, $f['user']->id);

    Event::assertDispatched(NegativeStockIssued::class);
});

it('refuses to overdraw a store that does not allow negative stock (BR-FIN-09-005)', function (): void {
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create();
    fin09Receive($f, $item, 5, 100);

    $requisition = app(RequestStoreRequisitionAction::class)->execute(new RequestStoreRequisitionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $f['store']->id, costCentreId: $f['store']->cost_centre_id, purpose: 'Overdraw test',
        requestedByUserId: $f['user']->id, currency: 'USD',
        lines: [['itemId' => $item->id, 'quantity' => 8, 'unit' => $item->base_unit]],
    ));
    app(ApproveStoreRequisitionAction::class)->execute($requisition->id, $f['user']->id);

    expect(fn () => app(IssueStockAction::class)->execute($requisition->id, $f['user']->id))
        ->toThrow(InsufficientStockException::class);
});

it('refuses to issue into a locked financial period (BR-FIN-09-025)', function (): void {
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create();
    fin09Receive($f, $item, 5, 100);

    $requisition = app(RequestStoreRequisitionAction::class)->execute(new RequestStoreRequisitionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $f['store']->id, costCentreId: $f['store']->cost_centre_id, purpose: 'Locked period test',
        requestedByUserId: $f['user']->id, currency: 'USD',
        lines: [['itemId' => $item->id, 'quantity' => 2, 'unit' => $item->base_unit]],
    ));
    app(ApproveStoreRequisitionAction::class)->execute($requisition->id, $f['user']->id);

    // financial_state can only change through ACT-TransitionPeriodState
    // (GuardsPeriodStateWrites) — soft-closing it is enough to prove the
    // refusal, without needing a close checklist to pass for a full lock.
    app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $f['term']->id, periodType: PeriodType::Financial, toState: PeriodState::SoftClosed,
        performedByUserId: $f['user']->id,
    ));

    expect(fn () => app(IssueStockAction::class)->execute($requisition->id, $f['user']->id))
        ->toThrow(PeriodLockedException::class);
});

it('detects a consumption anomaly beyond tolerance and never auto-dismisses the investigation (BR-FIN-09-022/023)', function (): void {
    Event::fake([ConsumptionAnomalyDetected::class]);
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create(['is_high_risk' => true]);
    fin09Receive($f, $item, 10000, 100);

    // Baseline: 10 units/day issued for the last 30 days.
    for ($i = 30; $i >= 1; $i--) {
        StockMovement::factory()->create([
            'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
            'store_id' => $f['store']->id, 'item_id' => $item->id, 'movement_type' => 'issue',
            'direction' => 'out', 'quantity' => 10, 'unit_cost_minor' => 100, 'total_cost_minor' => 1000,
            'base_total_minor' => 1000, 'balance_after' => 0, 'performed_by' => $f['user']->id,
            'occurred_at' => now()->subDays($i),
        ]);
    }

    app(ComputeConsumptionBaselineAction::class)->execute($f['store']->id, $item->id, 'daily', 30);

    // This week: 40 units/day — well beyond a 15% tolerance.
    for ($i = 6; $i >= 0; $i--) {
        StockMovement::factory()->create([
            'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
            'store_id' => $f['store']->id, 'item_id' => $item->id, 'movement_type' => 'issue',
            'direction' => 'out', 'quantity' => 40, 'unit_cost_minor' => 100, 'total_cost_minor' => 4000,
            'base_total_minor' => 4000, 'balance_after' => 0, 'performed_by' => $f['user']->id,
            'occurred_at' => now()->subDays($i),
        ]);
    }

    $anomaly = app(DetectConsumptionAnomalyAction::class)->execute($f['store']->id, $item->id, now()->subDays(6), now());

    expect($anomaly)->not->toBeNull()
        ->and($anomaly->severity)->toBe('high');
    Event::assertDispatched(ConsumptionAnomalyDetected::class);

    expect(fn () => app(RecordAnomalyInvestigationAction::class)->execute($anomaly->id, '', $f['user']->id, true))
        ->toThrow(ValidationException::class);

    $escalated = app(RecordAnomalyInvestigationAction::class)->execute($anomaly->id, 'No plausible explanation found from kitchen staff.', $f['user']->id, false);
    expect($escalated->status)->toBe('escalated');
});

it('resolves an anomaly without escalation once explained on a non-high-risk item', function (): void {
    $f = fin09Fixture();
    $anomaly = ConsumptionAnomaly::factory()->create([
        'school_id' => $f['school']->id, 'store_id' => $f['store']->id,
        'item_id' => InventoryItem::factory()->for($f['school'])->create(['is_high_risk' => false])->id,
    ]);

    $resolved = app(RecordAnomalyInvestigationAction::class)->execute($anomaly->id, 'Extra consumption due to a documented school event.', $f['user']->id, true);

    expect($resolved->status)->toBe('resolved');
});

it('fires ReorderLevelBreached only for items at or below their configured reorder level (BR-FIN-09-024)', function (): void {
    Event::fake([ReorderLevelBreached::class]);
    $f = fin09Fixture();
    $lowItem = InventoryItem::factory()->for($f['school'])->create();
    $healthyItem = InventoryItem::factory()->for($f['school'])->create();
    fin09Receive($f, $lowItem, 2, 100);
    fin09Receive($f, $healthyItem, 200, 100);
    StoreItemSetting::factory()->create(['school_id' => $f['school']->id, 'store_id' => $f['store']->id, 'item_id' => $lowItem->id, 'reorder_level' => 5]);
    StoreItemSetting::factory()->create(['school_id' => $f['school']->id, 'store_id' => $f['store']->id, 'item_id' => $healthyItem->id, 'reorder_level' => 5]);

    $breached = app(CheckReorderLevelsAction::class)->execute($f['store']->id);

    expect($breached)->toHaveCount(1);
    Event::assertDispatched(ReorderLevelBreached::class, 1);
});

it('fires ExpiryApproaching only when a lot crosses one of the configured alert windows (inventory.expiry_alert_days)', function (): void {
    Event::fake([ExpiryApproaching::class]);
    $f = fin09Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create(['is_perishable' => true]);
    fin09Receive($f, $item, 10, 100, now()->addDays(7)->toDateString());
    fin09Receive($f, $item, 10, 100, now()->addDays(45)->toDateString());

    $expiring = app(CheckExpiringLotsAction::class)->execute($f['school']->id);

    expect($expiring)->toHaveCount(1);
    Event::assertDispatched(ExpiryApproaching::class, 1);
});

it('rebinds Boarding\'s StoreIssuanceProvider to a real, store-backed implementation (closing BRD-04\'s deferral)', function (): void {
    $f = fin09Fixture();
    $kitchen = Store::factory()->for($f['school'])->kitchen()->create();
    $item = InventoryItem::factory()->for($f['school'])->create();
    fin09Receive($f, $item, 20, 250, store: $kitchen);

    $provider = app(StoreIssuanceProvider::class);

    expect($provider)->toBeInstanceOf(EloquentStoreIssuanceProvider::class)
        ->and($provider->currentCostMinor($f['school']->id, $item->id))->toBe(250)
        ->and($provider->checkAvailability($f['school']->id, $item->id, 15))->toBeTrue()
        ->and($provider->checkAvailability($f['school']->id, $item->id, 25))->toBeFalse();
});
