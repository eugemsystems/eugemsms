<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Farm\Domain\Actions\CheckMortalityRateAction;
use Modules\Farm\Domain\Actions\ComputeProfitabilityAction;
use Modules\Farm\Domain\Actions\ComputeSavingsReportAction;
use Modules\Farm\Domain\Actions\CreateFarmFieldAction;
use Modules\Farm\Domain\Actions\CreateLivestockAction;
use Modules\Farm\Domain\Actions\CreateProductionUnitAction;
use Modules\Farm\Domain\Actions\FailCropCycleAction;
use Modules\Farm\Domain\Actions\PlanCropCycleAction;
use Modules\Farm\Domain\Actions\RecordFarmSaleAction;
use Modules\Farm\Domain\Actions\RecordHarvestAction;
use Modules\Farm\Domain\Actions\RecordLivestockEventAction;
use Modules\Farm\Domain\Actions\RecordProductionOutputAction;
use Modules\Farm\Domain\Actions\TransferToKitchenAction;
use Modules\Farm\Domain\DataObjects\CreateFarmFieldData;
use Modules\Farm\Domain\DataObjects\CreateLivestockData;
use Modules\Farm\Domain\DataObjects\CreateProductionUnitData;
use Modules\Farm\Domain\DataObjects\FailCropCycleData;
use Modules\Farm\Domain\DataObjects\PlanCropCycleData;
use Modules\Farm\Domain\DataObjects\RecordFarmSaleData;
use Modules\Farm\Domain\DataObjects\RecordHarvestData;
use Modules\Farm\Domain\DataObjects\RecordLivestockEventData;
use Modules\Farm\Domain\DataObjects\RecordProductionOutputData;
use Modules\Farm\Domain\DataObjects\TransferToKitchenData;
use Modules\Farm\Domain\Events\CropCycleFailed;
use Modules\Farm\Domain\Events\MortalityRateExceeded;
use Modules\Farm\Domain\Events\WithdrawalPeriodBlocked;
use Modules\Farm\Domain\Exceptions\WithdrawalPeriodActiveException;
use Modules\Farm\Models\ProductionUnit;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\Journal;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\FixedAsset;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\Store;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, user2: User, costCentre: CostCentre, farmStore: Store, kitchenStore: Store, item: InventoryItem, farmProductionAccount: Account}
 */
function ops03Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open', 'is_current' => true]);
    $user = User::factory()->create();
    $user2 = User::factory()->create();

    foreach (['journal', 'store_requisition', 'stock_transfer', 'internal_transfer', 'farm_sale', 'fixed_asset'] as $type) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $type, pattern: strtoupper(substr($type, 0, 3)).'/{SEQ:5}',
        ));
    }

    $costCentre = CostCentre::factory()->for($school)->create();
    $farmStore = Store::factory()->for($school)->create(['code' => 'FARM']);
    $kitchenStore = Store::factory()->for($school)->create(['code' => 'KITCHEN']);
    $item = InventoryItem::factory()->for($school)->create(['base_unit' => 'kg']);
    $farmProductionAccount = Account::factory()->for($school)->create(['code' => 'FARM-PRODUCTION']);

    return compact('school', 'year', 'term', 'user', 'user2', 'costCentre', 'farmStore', 'kitchenStore', 'item', 'farmProductionAccount');
}

/**
 * @param  array<string, mixed>  $f
 */
function ops03Unit(array $f): ProductionUnit
{
    return app(CreateProductionUnitAction::class)->execute(new CreateProductionUnitData(
        schoolId: $f['school']->id, code: 'CROP-'.fake()->unique()->numberBetween(1, 9999), name: 'Vegetable Garden',
        unitType: 'crop', costCentreId: $f['costCentre']->id, storeId: $f['farmStore']->id,
    ));
}

it('plans a crop cycle and records a harvest at real cost per kg, creating a real FIN-09 farm store lot (BR-OPS-03-005/006/AC-OPS-03-001)', function (): void {
    $f = ops03Fixture();
    $unit = ops03Unit($f);
    $field = app(CreateFarmFieldAction::class)->execute(new CreateFarmFieldData(
        schoolId: $f['school']->id, productionUnitId: $unit->id, code: 'F1', name: 'Field 1', areaHectares: 2,
    ));

    $cycle = app(PlanCropCycleAction::class)->execute(new PlanCropCycleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, productionUnitId: $unit->id, fieldId: $field->id,
        cycleReference: 'CYC-'.fake()->unique()->numberBetween(1000, 9999), crop: 'Tomatoes', season: 'summer',
        areaPlantedHectares: 2, currency: 'USD',
    ));
    $cycle->update(['input_cost_minor' => 90000, 'labour_cost_minor' => 40000, 'overhead_cost_minor' => 14000, 'total_cost_minor' => 144000]);

    $harvest = app(RecordHarvestAction::class)->execute(new RecordHarvestData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, cropCycleId: $cycle->id,
        harvestedOn: Carbon::now(), quantityKg: 1800, itemId: $f['item']->id,
        farmProductionContraAccountId: $f['farmProductionAccount']->id, recordedByUserId: $f['user']->id,
    ));

    expect($harvest->unit_cost_minor)->toBe(80)
        ->and($cycle->fresh()->cost_per_kg_minor)->toBe(80)
        ->and($harvest->stock_lot_id)->not->toBeNull();

    $lot = StockLot::find($harvest->stock_lot_id);
    expect($lot->store_id)->toBe($f['farmStore']->id)
        ->and((float) $lot->quantity_remaining)->toBe(1800.0)
        ->and($lot->unit_cost_minor)->toBe(80);
});

it('transfers produce to the kitchen at internal cost with a real FIN-09 journal and lot (BR-OPS-03-008/009/AC-OPS-03-002)', function (): void {
    $f = ops03Fixture();
    $unit = ops03Unit($f);
    $field = app(CreateFarmFieldAction::class)->execute(new CreateFarmFieldData(
        schoolId: $f['school']->id, productionUnitId: $unit->id, code: 'F2', name: 'Field 2', areaHectares: 2,
    ));
    $cycle = app(PlanCropCycleAction::class)->execute(new PlanCropCycleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, productionUnitId: $unit->id, fieldId: $field->id,
        cycleReference: 'CYC-'.fake()->unique()->numberBetween(1000, 9999), crop: 'Tomatoes', season: 'summer',
        areaPlantedHectares: 2, currency: 'USD',
    ));
    $cycle->update(['total_cost_minor' => 144000]);
    $harvest = app(RecordHarvestAction::class)->execute(new RecordHarvestData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, cropCycleId: $cycle->id,
        harvestedOn: Carbon::now(), quantityKg: 1800, itemId: $f['item']->id,
        farmProductionContraAccountId: $f['farmProductionAccount']->id, recordedByUserId: $f['user']->id,
    ));

    $transfer = app(TransferToKitchenAction::class)->execute(new TransferToKitchenData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        productionUnitId: $unit->id, fromStoreId: $f['farmStore']->id, toStoreId: $f['kitchenStore']->id,
        itemId: $f['item']->id, quantity: 200, unit: 'kg', transferDate: Carbon::now(),
        dispatchedByUserId: $f['user']->id, harvestId: $harvest->id, marketPriceMinor: 120,
    ));

    expect($transfer->status)->toBe('received')
        ->and($transfer->total_cost_minor)->toBe(16000)
        ->and($transfer->unit_cost_minor)->toBe(80);

    $journal = Journal::find($transfer->journal_id);
    $drLine = $journal->lines->firstWhere('direction', 'DR');
    expect($journal->lines)->toHaveCount(2)
        ->and($drLine->amount_minor)->toBe(16000);

    $kitchenLot = StockLot::where('store_id', $f['kitchenStore']->id)->where('item_id', $f['item']->id)->first();
    expect($kitchenLot)->not->toBeNull()
        ->and($kitchenLot->unit_cost_minor)->toBe(80);
});

it('blocks a kitchen transfer of milk from livestock still within its withdrawal period, naming the period and end date (BR-OPS-03-012/AC-OPS-03-003)', function (): void {
    Event::fake([WithdrawalPeriodBlocked::class]);
    $f = ops03Fixture();
    $unit = app(CreateProductionUnitAction::class)->execute(new CreateProductionUnitData(
        schoolId: $f['school']->id, code: 'DAIRY-1', name: 'Dairy Unit', unitType: 'dairy',
        costCentreId: $f['costCentre']->id, storeId: $f['farmStore']->id,
    ));
    $cow = app(CreateLivestockAction::class)->execute(new CreateLivestockData(
        schoolId: $f['school']->id, productionUnitId: $unit->id, species: 'cattle', purpose: 'dairy',
        tagNumber: 'COW-1',
    ));

    app(RecordLivestockEventAction::class)->execute(new RecordLivestockEventData(
        schoolId: $f['school']->id, livestockId: $cow->id, eventType: 'treatment',
        eventDate: Carbon::parse('2026-09-01'), recordedByUserId: $f['user']->id,
        medication: 'Antibiotic X', withdrawalPeriodDays: 7,
    ));

    $output = app(RecordProductionOutputAction::class)->execute(new RecordProductionOutputData(
        schoolId: $f['school']->id, productionUnitId: $unit->id, outputDate: Carbon::parse('2026-09-05'),
        outputType: 'milk', quantity: 50, unit: 'litres', currency: 'USD', recordedByUserId: $f['user']->id,
    ));

    expect(fn () => app(TransferToKitchenAction::class)->execute(new TransferToKitchenData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        productionUnitId: $unit->id, fromStoreId: $f['farmStore']->id, toStoreId: $f['kitchenStore']->id,
        itemId: $f['item']->id, quantity: 50, unit: 'litres', transferDate: Carbon::parse('2026-09-05'),
        dispatchedByUserId: $f['user']->id, outputId: $output->id,
    )))->toThrow(WithdrawalPeriodActiveException::class);

    Event::assertDispatched(WithdrawalPeriodBlocked::class);
});

it('writes off a failed crop cycle to a dedicated failure expense account with the reason recorded (BR-OPS-03-007/AC-OPS-03-004)', function (): void {
    Event::fake([CropCycleFailed::class]);
    $f = ops03Fixture();
    $unit = ops03Unit($f);
    $field = app(CreateFarmFieldAction::class)->execute(new CreateFarmFieldData(
        schoolId: $f['school']->id, productionUnitId: $unit->id, code: 'F3', name: 'Field 3', areaHectares: 1,
    ));
    $cycle = app(PlanCropCycleAction::class)->execute(new PlanCropCycleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, productionUnitId: $unit->id, fieldId: $field->id,
        cycleReference: 'CYC-'.fake()->unique()->numberBetween(1000, 9999), crop: 'Rape', season: 'winter',
        areaPlantedHectares: 1, currency: 'USD',
    ));
    $cycle->update(['total_cost_minor' => 50000]);

    $failureAccount = Account::factory()->for($f['school'])->expense()->create(['code' => 'CROP-FAIL']);
    $originalAccount = Account::factory()->for($f['school'])->expense()->create(['code' => 'FARM-EXP']);

    $failed = app(FailCropCycleAction::class)->execute($cycle->id, new FailCropCycleData(
        academicYearId: $f['year']->id, termId: $f['term']->id, failureReason: 'Drought destroyed the crop.',
        cropFailureExpenseAccountId: $failureAccount->id, originalExpenseAccountId: $originalAccount->id,
        performedByUserId: $f['user']->id,
    ));

    expect($failed->status)->toBe('failed')
        ->and($failed->failure_reason)->toBe('Drought destroyed the crop.');
    Event::assertDispatched(CropCycleFailed::class);

    $journal = Journal::where('school_id', $f['school']->id)->where('journal_type', 'CROP_FAILURE_WRITEOFF')->first();
    expect($journal)->not->toBeNull()
        ->and($journal->lines->firstWhere('direction', 'DR')->account_id)->toBe($failureAccount->id);
});

it('reports the savings from transferring produce at internal cost against market price (AC-OPS-03-005)', function (): void {
    $f = ops03Fixture();
    $unit = ops03Unit($f);
    $field = app(CreateFarmFieldAction::class)->execute(new CreateFarmFieldData(
        schoolId: $f['school']->id, productionUnitId: $unit->id, code: 'F4', name: 'Field 4', areaHectares: 2,
    ));
    $cycle = app(PlanCropCycleAction::class)->execute(new PlanCropCycleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, productionUnitId: $unit->id, fieldId: $field->id,
        cycleReference: 'CYC-'.fake()->unique()->numberBetween(1000, 9999), crop: 'Tomatoes', season: 'summer',
        areaPlantedHectares: 2, currency: 'USD',
    ));
    $cycle->update(['total_cost_minor' => 144000]);
    $harvest = app(RecordHarvestAction::class)->execute(new RecordHarvestData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, cropCycleId: $cycle->id,
        harvestedOn: Carbon::now(), quantityKg: 1800, itemId: $f['item']->id,
        farmProductionContraAccountId: $f['farmProductionAccount']->id, recordedByUserId: $f['user']->id,
    ));

    app(TransferToKitchenAction::class)->execute(new TransferToKitchenData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        productionUnitId: $unit->id, fromStoreId: $f['farmStore']->id, toStoreId: $f['kitchenStore']->id,
        itemId: $f['item']->id, quantity: 200, unit: 'kg', transferDate: Carbon::now(),
        dispatchedByUserId: $f['user']->id, harvestId: $harvest->id, marketPriceMinor: 120,
    ));

    $result = app(ComputeSavingsReportAction::class)->execute($f['school']->id, Carbon::now()->subDay(), Carbon::now()->addDay());

    expect($result->marketValueMinor)->toBe(24000)
        ->and($result->internalCostMinor)->toBe(16000)
        ->and($result->savingsMinor())->toBe(8000);
});

it('posts a real journal for an external farm sale (BR-OPS-03-016/AC-OPS-03-006)', function (): void {
    $f = ops03Fixture();
    $unit = ops03Unit($f);
    $cashAccount = Account::factory()->for($f['school'])->create(['code' => 'CASH']);
    $incomeAccount = Account::factory()->for($f['school'])->create(['code' => 'FARM-SALES-INCOME']);

    $sale = app(RecordFarmSaleAction::class)->execute(new RecordFarmSaleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        productionUnitId: $unit->id, saleDate: Carbon::now(), buyerName: 'Local Grocer',
        itemDescription: 'Surplus vegetables', quantity: 50, unit: 'kg', unitPriceMinor: 100,
        currency: 'USD', cashAccountId: $cashAccount->id, salesIncomeAccountId: $incomeAccount->id,
        performedByUserId: $f['user']->id,
    ));

    expect($sale->total_minor)->toBe(5000)
        ->and($sale->fiscal_receipt_id)->toBeNull();

    $journal = Journal::find($sale->journal_id);
    expect($journal->lines)->toHaveCount(2)
        ->and($journal->lines->firstWhere('direction', 'CR')->account_id)->toBe($incomeAccount->id);
});

it('alerts when mortality exceeds the configured rate (BR-OPS-03-014/AC-OPS-03-007)', function (): void {
    Event::fake([MortalityRateExceeded::class]);
    $f = ops03Fixture();
    $unit = app(CreateProductionUnitAction::class)->execute(new CreateProductionUnitData(
        schoolId: $f['school']->id, code: 'POULTRY-1', name: 'Poultry Unit', unitType: 'poultry',
        costCentreId: $f['costCentre']->id,
    ));
    $flock = app(CreateLivestockAction::class)->execute(new CreateLivestockData(
        schoolId: $f['school']->id, productionUnitId: $unit->id, species: 'poultry', purpose: 'broilers',
        isHerdRecord: true, headCount: 95,
    ));

    // 10 of an original 105 died — 10/105 ≈ 9.5%, above the default 5%.
    app(RecordLivestockEventAction::class)->execute(new RecordLivestockEventData(
        schoolId: $f['school']->id, livestockId: $flock->id, eventType: 'death', eventDate: Carbon::now(),
        recordedByUserId: $f['user']->id, headCountAffected: 10, description: 'Heat stress.',
    ));

    $mortalityPercent = app(CheckMortalityRateAction::class)->execute($unit->id, Carbon::now()->subDay(), Carbon::now()->addDay());

    expect($mortalityPercent)->toBeGreaterThan(5.0);
    Event::assertDispatched(MortalityRateExceeded::class);
});

it('capitalises breeding stock above threshold to a real FIN-10 fixed asset (BR-OPS-03-011)', function (): void {
    $f = ops03Fixture();
    $unit = app(CreateProductionUnitAction::class)->execute(new CreateProductionUnitData(
        schoolId: $f['school']->id, code: 'DAIRY-2', name: 'Dairy Unit 2', unitType: 'dairy',
        costCentreId: $f['costCentre']->id,
    ));

    $assetAccount = Account::factory()->for($f['school'])->create(['code' => 'LIVESTOCK-ASSET']);
    $accumDepAccount = Account::factory()->for($f['school'])->create(['code' => 'LIVESTOCK-ACCDEP']);
    $depExpenseAccount = Account::factory()->for($f['school'])->expense()->create(['code' => 'LIVESTOCK-DEPEXP']);
    $disposalAccount = Account::factory()->for($f['school'])->create(['code' => 'LIVESTOCK-DISPOSAL']);
    $category = AssetCategory::factory()->create([
        'school_id' => $f['school']->id, 'code' => 'LIVESTOCK', 'asset_account_id' => $assetAccount->id,
        'accum_depreciation_account_id' => $accumDepAccount->id, 'depreciation_expense_account_id' => $depExpenseAccount->id,
        'disposal_account_id' => $disposalAccount->id, 'is_depreciable' => false,
    ]);
    $contraAccount = Account::factory()->for($f['school'])->create(['code' => 'AP-LIVESTOCK']);

    $cow = app(CreateLivestockAction::class)->execute(new CreateLivestockData(
        schoolId: $f['school']->id, productionUnitId: $unit->id, species: 'cattle', purpose: 'breeding',
        tagNumber: 'BULL-1', acquisitionCostMinor: 500000, currency: 'USD', acquiredOn: Carbon::now(),
        acquisitionType: 'purchase', capitalizeCategoryId: $category->id, capitalizeCostCentreId: $f['costCentre']->id,
        capitalizeContraAccountId: $contraAccount->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        performedByUserId: $f['user']->id,
    ));

    expect($cow->fixed_asset_id)->not->toBeNull();

    $asset = FixedAsset::find($cow->fixed_asset_id);
    expect($asset->acquisition_cost_minor)->toBe(500000)
        ->and($asset->category_id)->toBe($category->id);
});

it('computes per-unit profitability from crop cost, kitchen transfer value and external sales (BR-OPS-03-017)', function (): void {
    $f = ops03Fixture();
    $unit = ops03Unit($f);
    $field = app(CreateFarmFieldAction::class)->execute(new CreateFarmFieldData(
        schoolId: $f['school']->id, productionUnitId: $unit->id, code: 'F5', name: 'Field 5', areaHectares: 2,
    ));
    $cycle = app(PlanCropCycleAction::class)->execute(new PlanCropCycleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, productionUnitId: $unit->id, fieldId: $field->id,
        cycleReference: 'CYC-'.fake()->unique()->numberBetween(1000, 9999), crop: 'Tomatoes', season: 'summer',
        areaPlantedHectares: 2, currency: 'USD',
    ));
    $cycle->update(['total_cost_minor' => 144000]);
    $harvest = app(RecordHarvestAction::class)->execute(new RecordHarvestData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, cropCycleId: $cycle->id,
        harvestedOn: Carbon::now(), quantityKg: 1800, itemId: $f['item']->id,
        farmProductionContraAccountId: $f['farmProductionAccount']->id, recordedByUserId: $f['user']->id,
    ));
    app(TransferToKitchenAction::class)->execute(new TransferToKitchenData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        productionUnitId: $unit->id, fromStoreId: $f['farmStore']->id, toStoreId: $f['kitchenStore']->id,
        itemId: $f['item']->id, quantity: 200, unit: 'kg', transferDate: Carbon::now(),
        dispatchedByUserId: $f['user']->id, harvestId: $harvest->id,
    ));

    $cashAccount = Account::factory()->for($f['school'])->create(['code' => 'CASH2']);
    $incomeAccount = Account::factory()->for($f['school'])->create(['code' => 'FARM-SALES-INCOME2']);
    app(RecordFarmSaleAction::class)->execute(new RecordFarmSaleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        productionUnitId: $unit->id, saleDate: Carbon::now(), buyerName: 'Local Grocer',
        itemDescription: 'Surplus tomatoes', quantity: 100, unit: 'kg', unitPriceMinor: 100,
        currency: 'USD', cashAccountId: $cashAccount->id, salesIncomeAccountId: $incomeAccount->id,
        performedByUserId: $f['user']->id,
    ));

    $result = app(ComputeProfitabilityAction::class)->execute($unit->id, $f['term']->id, Carbon::now()->subDay(), Carbon::now()->addDay());

    expect($result->totalCropCostMinor)->toBe(144000)
        ->and($result->kitchenTransferValueMinor)->toBe(16000)
        ->and($result->externalSalesMinor)->toBe(10000)
        ->and($result->netPositionMinor())->toBe(16000 + 10000 - 144000);
});
