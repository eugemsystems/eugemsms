<?php

use App\Models\User;
use Illuminate\Support\Carbon;
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
use Modules\Finance\Models\Journal;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Operations\Models\MaintenanceSchedule;
use Modules\Operations\Models\WorkOrder;
use Modules\Stores\Domain\Actions\ReceiveStockAction;
use Modules\Stores\Domain\DataObjects\ReceiveStockData;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreRequisition;
use Modules\Utilities\Domain\Actions\CheckTokenReconciliationAction;
use Modules\Utilities\Domain\Actions\CheckUncreditedTokensAction;
use Modules\Utilities\Domain\Actions\ComputeOutageCostAction;
use Modules\Utilities\Domain\Actions\ConfirmTokenCreditAction;
use Modules\Utilities\Domain\Actions\CreateGeneratorAction;
use Modules\Utilities\Domain\Actions\CreateMeterAction;
use Modules\Utilities\Domain\Actions\CreateUtilityAccountAction;
use Modules\Utilities\Domain\Actions\CreateWaterSourceAction;
use Modules\Utilities\Domain\Actions\PurchasePrepaidTokenAction;
use Modules\Utilities\Domain\Actions\RecordMeterReadingAction;
use Modules\Utilities\Domain\Actions\RecordWaterQualityTestAction;
use Modules\Utilities\Domain\Actions\RecordWaterReadingAction;
use Modules\Utilities\Domain\Actions\StartGeneratorRunAction;
use Modules\Utilities\Domain\Actions\StopGeneratorRunAction;
use Modules\Utilities\Domain\DataObjects\CreateGeneratorData;
use Modules\Utilities\Domain\DataObjects\CreateMeterData;
use Modules\Utilities\Domain\DataObjects\CreateUtilityAccountData;
use Modules\Utilities\Domain\DataObjects\CreateWaterSourceData;
use Modules\Utilities\Domain\DataObjects\PurchasePrepaidTokenData;
use Modules\Utilities\Domain\DataObjects\RecordMeterReadingData;
use Modules\Utilities\Domain\DataObjects\RecordWaterQualityTestData;
use Modules\Utilities\Domain\DataObjects\RecordWaterReadingData;
use Modules\Utilities\Domain\DataObjects\StartGeneratorRunData;
use Modules\Utilities\Domain\DataObjects\StopGeneratorRunData;
use Modules\Utilities\Domain\Events\BoreholeYieldReduced;
use Modules\Utilities\Domain\Events\GeneratorFuelAnomaly;
use Modules\Utilities\Domain\Events\MeterReadingAnomaly;
use Modules\Utilities\Domain\Events\TokenReconciliationVariance;
use Modules\Utilities\Domain\Events\TokenUncredited;
use Modules\Utilities\Domain\Events\WaterQualityFailed;
use Modules\Utilities\Domain\Events\WaterStorageLow;
use Modules\Utilities\Models\Meter;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, user2: User, costCentre: CostCentre, expenseAccount: Account, prepaidAccount: Account, bankAccount: Account}
 */
function ops04Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open', 'is_current' => true]);
    $user = User::factory()->create();
    $user2 = User::factory()->create();

    foreach (['journal', 'work_order', 'store_requisition'] as $type) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $type, pattern: strtoupper(substr($type, 0, 3)).'/{SEQ:5}',
        ));
    }

    $costCentre = CostCentre::factory()->for($school)->create();
    $expenseAccount = Account::factory()->for($school)->expense()->create(['code' => 'ELEC-EXP']);
    $prepaidAccount = Account::factory()->for($school)->create(['code' => 'PREPAID-ELEC']);
    $bankAccount = Account::factory()->for($school)->create(['code' => 'BANK']);

    return compact('school', 'year', 'term', 'user', 'user2', 'costCentre', 'expenseAccount', 'prepaidAccount', 'bankAccount');
}

/**
 * @param  array<string, mixed>  $f
 */
function ops04Meter(array $f, string $billingMode = 'prepaid'): Meter
{
    $account = app(CreateUtilityAccountAction::class)->execute(new CreateUtilityAccountData(
        schoolId: $f['school']->id, utilityType: 'electricity', provider: 'ZESA',
        accountNumber: 'ACC-'.fake()->unique()->numberBetween(10000, 99999), billingMode: $billingMode,
        costCentreId: $f['costCentre']->id, expenseAccountId: $f['expenseAccount']->id,
    ));

    return app(CreateMeterAction::class)->execute(new CreateMeterData(
        schoolId: $f['school']->id, utilityAccountId: $account->id, meterNumber: 'MTR-'.fake()->unique()->numberBetween(10000, 99999),
        meterType: 'electricity_prepaid', location: 'Main switchroom', servesScope: 'whole_school', unit: 'kWh',
        costCentreId: $f['costCentre']->id,
    ));
}

it('records a prepaid token purchase as a prepayment asset, refuses a duplicate token, and requires confirmation to credit (BR-OPS-04-001/003/004/AC-OPS-04-002)', function (): void {
    $f = ops04Fixture();
    $meter = ops04Meter($f);

    $purchase = app(PurchasePrepaidTokenAction::class)->execute(new PurchasePrepaidTokenData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, meterId: $meter->id,
        purchasedAt: Carbon::now(), tokenNumber: '1111-2222-3333-4444', amountPaidMinor: 20000, currency: 'USD',
        unitsPurchased: 100, prepaidAssetAccountId: $f['prepaidAccount']->id, contraAccountId: $f['bankAccount']->id,
        purchasedByUserId: $f['user']->id,
    ));

    expect($purchase->status)->toBe('purchased')
        ->and($purchase->credit_confirmed)->toBeFalse();

    $journal = Journal::find($purchase->journal_id);
    expect($journal->lines->firstWhere('direction', 'DR')->account_id)->toBe($f['prepaidAccount']->id);

    expect(fn () => app(PurchasePrepaidTokenAction::class)->execute(new PurchasePrepaidTokenData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, meterId: $meter->id,
        purchasedAt: Carbon::now(), tokenNumber: '1111-2222-3333-4444', amountPaidMinor: 20000, currency: 'USD',
        unitsPurchased: 100, prepaidAssetAccountId: $f['prepaidAccount']->id, contraAccountId: $f['bankAccount']->id,
        purchasedByUserId: $f['user']->id,
    )))->toThrow(ValidationException::class);

    $confirmed = app(ConfirmTokenCreditAction::class)->execute($purchase->id, $f['user2']->id);
    expect($confirmed->credit_confirmed)->toBeTrue()
        ->and($confirmed->status)->toBe('credited')
        ->and($meter->fresh()->current_balance_units)->toBe('100.000');
});

it('alerts on a token left uncredited beyond the configured window (BR-OPS-04-002/AC-OPS-04-001)', function (): void {
    Event::fake([TokenUncredited::class]);
    $f = ops04Fixture();
    $meter = ops04Meter($f);

    $purchase = app(PurchasePrepaidTokenAction::class)->execute(new PurchasePrepaidTokenData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, meterId: $meter->id,
        purchasedAt: Carbon::now()->subHours(30), tokenNumber: '5555-6666-7777-8888', amountPaidMinor: 20000,
        currency: 'USD', unitsPurchased: 100, prepaidAssetAccountId: $f['prepaidAccount']->id,
        contraAccountId: $f['bankAccount']->id, purchasedByUserId: $f['user']->id,
    ));

    $uncredited = app(CheckUncreditedTokensAction::class)->execute($f['school']->id);

    expect($uncredited)->toHaveCount(1)
        ->and($uncredited->first()->id)->toBe($purchase->id);
    Event::assertDispatched(TokenUncredited::class);
});

it('flags a meter reading lower than the previous one as an anomaly, and recognises real consumption expense on a prepaid meter (BR-OPS-04-004/006/007/009/AC-OPS-04-004)', function (): void {
    Event::fake([MeterReadingAnomaly::class]);
    $f = ops04Fixture();
    $meter = ops04Meter($f);
    $purchase = app(PurchasePrepaidTokenAction::class)->execute(new PurchasePrepaidTokenData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, meterId: $meter->id,
        purchasedAt: Carbon::now()->subDays(10), tokenNumber: '9999-0000-1111-2222', amountPaidMinor: 20000,
        currency: 'USD', unitsPurchased: 100, prepaidAssetAccountId: $f['prepaidAccount']->id,
        contraAccountId: $f['bankAccount']->id, purchasedByUserId: $f['user']->id,
    ));
    app(ConfirmTokenCreditAction::class)->execute($purchase->id, $f['user2']->id);

    $first = app(RecordMeterReadingAction::class)->execute(new RecordMeterReadingData(
        schoolId: $f['school']->id, meterId: $meter->id, readOn: Carbon::now()->subDays(5),
        reading: 1000, readingMethod: 'manual', readByUserId: $f['user']->id,
    ));
    expect($first->is_anomaly)->toBeFalse();

    $second = app(RecordMeterReadingAction::class)->execute(new RecordMeterReadingData(
        schoolId: $f['school']->id, meterId: $meter->id, readOn: Carbon::now()->subDays(2),
        reading: 900, readingMethod: 'manual', readByUserId: $f['user']->id,
    ));

    expect($second->is_anomaly)->toBeTrue()
        ->and($second->consumption)->toBeNull();
    Event::assertDispatched(MeterReadingAnomaly::class);

    $third = app(RecordMeterReadingAction::class)->execute(new RecordMeterReadingData(
        schoolId: $f['school']->id, meterId: $meter->id, readOn: Carbon::now(),
        reading: 1050, readingMethod: 'manual', readByUserId: $f['user']->id,
        academicYearId: $f['year']->id, termId: $f['term']->id,
        prepaidAssetAccountId: $f['prepaidAccount']->id, postedByUserId: $f['user']->id,
    ));

    // Previous is the second reading (900) — the anomalous first-lower
    // entry sets its own previous_reading but never computes a
    // consumption figure for the anomaly itself.
    expect((float) $third->consumption)->toBe(150.0)
        ->and($third->is_anomaly)->toBeFalse();

    $expenseJournal = Journal::where('school_id', $f['school']->id)->where('journal_type', 'UTILITY_CONSUMPTION_EXPENSE')->first();
    expect($expenseJournal)->not->toBeNull();
    $drLine = $expenseJournal->lines->firstWhere('direction', 'DR');
    expect($drLine->account_id)->toBe($f['expenseAccount']->id)
        ->and($drLine->cost_centre_id)->toBe($f['costCentre']->id);
});

it('flags a token reconciliation variance between credited units and metered consumption (BR-OPS-04-005/AC-OPS-04-003)', function (): void {
    Event::fake([TokenReconciliationVariance::class]);
    $f = ops04Fixture();
    $meter = ops04Meter($f);
    $purchase = app(PurchasePrepaidTokenAction::class)->execute(new PurchasePrepaidTokenData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, meterId: $meter->id,
        purchasedAt: Carbon::now()->subDays(20), tokenNumber: '1212-3434-5656-7878', amountPaidMinor: 84000,
        currency: 'USD', unitsPurchased: 4200, prepaidAssetAccountId: $f['prepaidAccount']->id,
        contraAccountId: $f['bankAccount']->id, purchasedByUserId: $f['user']->id,
    ));
    app(ConfirmTokenCreditAction::class)->execute($purchase->id, $f['user2']->id);

    app(RecordMeterReadingAction::class)->execute(new RecordMeterReadingData(
        schoolId: $f['school']->id, meterId: $meter->id, readOn: Carbon::now()->subDays(15),
        reading: 0, readingMethod: 'manual', readByUserId: $f['user']->id,
    ));
    app(RecordMeterReadingAction::class)->execute(new RecordMeterReadingData(
        schoolId: $f['school']->id, meterId: $meter->id, readOn: Carbon::now(),
        reading: 5100, readingMethod: 'manual', readByUserId: $f['user']->id,
    ));

    $flagged = app(CheckTokenReconciliationAction::class)->execute($f['school']->id, Carbon::now()->subDays(30), Carbon::now()->addDay());

    expect($flagged)->toHaveCount(1);
    Event::assertDispatched(TokenReconciliationVariance::class);
});

it('drains the FIN-09 school tank for diesel and triggers a real OPS-02 usage-based work order on generator stop (BR-OPS-04-011/012/AC-OPS-04-007)', function (): void {
    $f = ops04Fixture();
    $asset = MaintenanceAsset::factory()->for($f['school'])->create(['cost_centre_id' => $f['costCentre']->id]);
    $schedule = MaintenanceSchedule::factory()->create([
        'school_id' => $f['school']->id, 'maintenance_asset_id' => $asset->id, 'trigger_type' => 'usage',
        'interval_units' => 200, 'next_due_units' => 1,
    ]);
    $generator = app(CreateGeneratorAction::class)->execute(new CreateGeneratorData(
        schoolId: $f['school']->id, code: 'GEN-1', name: 'Main Generator', capacityKva: 100,
        costCentreId: $f['costCentre']->id, expectedLitresPerHour: 15, maintenanceAssetId: $asset->id,
    ));

    $store = Store::factory()->for($f['school'])->create();
    $item = InventoryItem::factory()->for($f['school'])->create(['base_unit' => 'litre']);
    app(ReceiveStockAction::class)->execute(new ReceiveStockData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $store->id, itemId: $item->id, quantity: 200, unitCostMinor: 150,
        currency: 'USD', receivedOn: now(), performedByUserId: $f['user']->id,
        contraAccountId: $f['expenseAccount']->id,
    ));

    $run = app(StartGeneratorRunAction::class)->execute(new StartGeneratorRunData(
        schoolId: $f['school']->id, termId: $f['term']->id, generatorId: $generator->id,
        startedAt: Carbon::now()->subHours(2), reason: 'load_shedding', operatedByUserId: $f['user']->id,
    ));

    $stopped = app(StopGeneratorRunAction::class)->execute($run->id, new StopGeneratorRunData(
        academicYearId: $f['year']->id, stoppedAt: Carbon::now(), dieselLitres: 30,
        dieselUnitPriceMinor: 150, currency: 'USD', storeId: $store->id, itemId: $item->id,
        operatedByUserId: $f['user']->id,
    ));

    expect((float) $stopped->hours_run)->toBe(2.0)
        ->and($stopped->store_requisition_id)->not->toBeNull()
        ->and($generator->fresh()->current_hours)->toEqualWithDelta(2.0, 0.001);

    $requisition = StoreRequisition::find($stopped->store_requisition_id);
    expect($requisition->status)->toBe('issued');

    $workOrder = WorkOrder::where('school_id', $f['school']->id)->where('schedule_id', $schedule->id)->first();
    expect($workOrder)->not->toBeNull();
});

it('flags a generator fuel anomaly against the expected litres-per-hour baseline (BR-OPS-04-012)', function (): void {
    Event::fake([GeneratorFuelAnomaly::class]);
    $f = ops04Fixture();
    $generator = app(CreateGeneratorAction::class)->execute(new CreateGeneratorData(
        schoolId: $f['school']->id, code: 'GEN-2', name: 'Backup Generator', capacityKva: 60,
        costCentreId: $f['costCentre']->id, expectedLitresPerHour: 10,
    ));

    $run = app(StartGeneratorRunAction::class)->execute(new StartGeneratorRunData(
        schoolId: $f['school']->id, termId: $f['term']->id, generatorId: $generator->id,
        startedAt: Carbon::now()->subHour(), reason: 'test', operatedByUserId: $f['user']->id,
    ));

    // 20 litres in 1 hour against an expected 10 L/h — a 100% variance.
    $stopped = app(StopGeneratorRunAction::class)->execute($run->id, new StopGeneratorRunData(
        academicYearId: $f['year']->id, stoppedAt: Carbon::now(), dieselLitres: 20,
        operatedByUserId: $f['user']->id,
    ));

    expect($stopped->is_anomaly)->toBeTrue();
    Event::assertDispatched(GeneratorFuelAnomaly::class);
});

it('raises a real OPS-02 work order and flags reduced yield when borehole yield falls below baseline (BR-OPS-04-015/AC-OPS-04-008)', function (): void {
    Event::fake([BoreholeYieldReduced::class]);
    $f = ops04Fixture();
    $asset = MaintenanceAsset::factory()->for($f['school'])->create(['cost_centre_id' => $f['costCentre']->id]);
    $source = app(CreateWaterSourceAction::class)->execute(new CreateWaterSourceData(
        schoolId: $f['school']->id, code: 'BH-1', name: 'Main Borehole', sourceType: 'borehole',
        yieldLitresPerHour: 2000, maintenanceAssetId: $asset->id,
    ));

    // 60% of the 2000 L/h baseline — below the default 70% alert threshold.
    $reading = app(RecordWaterReadingAction::class)->execute(new RecordWaterReadingData(
        schoolId: $f['school']->id, waterSourceId: $source->id, readOn: Carbon::now(),
        readByUserId: $f['user']->id, yieldObserved: 1200,
        academicYearId: $f['year']->id, termId: $f['term']->id, costCentreId: $f['costCentre']->id,
    ));

    expect($reading->yield_observed)->not->toBeNull()
        ->and($source->fresh()->status)->toBe('reduced_yield');
    Event::assertDispatched(BoreholeYieldReduced::class);

    $workOrder = WorkOrder::where('school_id', $f['school']->id)->where('maintenance_asset_id', $asset->id)->first();
    expect($workOrder)->not->toBeNull()
        ->and($workOrder->title)->toContain('Reduced yield');
});

it('alerts on low water storage (BR-OPS-04-016)', function (): void {
    Event::fake([WaterStorageLow::class]);
    $f = ops04Fixture();
    $source = app(CreateWaterSourceAction::class)->execute(new CreateWaterSourceData(
        schoolId: $f['school']->id, code: 'BH-2', name: 'Second Borehole', sourceType: 'borehole',
        storageCapacityLitres: 50000,
    ));

    app(RecordWaterReadingAction::class)->execute(new RecordWaterReadingData(
        schoolId: $f['school']->id, waterSourceId: $source->id, readOn: Carbon::now(),
        readByUserId: $f['user']->id, storageLevelPercent: 15,
    ));

    Event::assertDispatched(WaterStorageLow::class);
});

it('flags a not_potable water quality result (BR-OPS-04-017/AC-OPS-04-009)', function (): void {
    Event::fake([WaterQualityFailed::class]);
    $f = ops04Fixture();
    $source = app(CreateWaterSourceAction::class)->execute(new CreateWaterSourceData(
        schoolId: $f['school']->id, code: 'BH-3', name: 'Third Borehole', sourceType: 'borehole',
    ));

    $updated = app(RecordWaterQualityTestAction::class)->execute(new RecordWaterQualityTestData(
        waterSourceId: $source->id, testedOn: Carbon::now(), qualityStatus: 'not_potable',
    ));

    expect($updated->water_quality_status)->toBe('not_potable');
    Event::assertDispatched(WaterQualityFailed::class);
});

it('computes real outage cost from generator hours, diesel, real OPS-02 maintenance and real FIN-10 depreciation (BR-OPS-04-011/AC-OPS-04-006)', function (): void {
    $f = ops04Fixture();
    $generator = app(CreateGeneratorAction::class)->execute(new CreateGeneratorData(
        schoolId: $f['school']->id, code: 'GEN-3', name: 'Costed Generator', capacityKva: 100,
        costCentreId: $f['costCentre']->id,
    ));

    $run = app(StartGeneratorRunAction::class)->execute(new StartGeneratorRunData(
        schoolId: $f['school']->id, termId: $f['term']->id, generatorId: $generator->id,
        startedAt: Carbon::now()->subHours(3), reason: 'load_shedding', operatedByUserId: $f['user']->id,
    ));
    app(StopGeneratorRunAction::class)->execute($run->id, new StopGeneratorRunData(
        academicYearId: $f['year']->id, stoppedAt: Carbon::now(), dieselLitres: 45,
        dieselUnitPriceMinor: 150, operatedByUserId: $f['user']->id,
    ));

    $result = app(ComputeOutageCostAction::class)->execute($f['school']->id, Carbon::now()->subDay(), Carbon::now()->addDay());

    expect($result->generatorKwh)->toEqualWithDelta(3.0 * 100 * 0.60, 0.001)
        ->and($result->outageHours)->toEqualWithDelta(3.0, 0.001)
        ->and($result->generatorCostMinor)->toBeGreaterThan(0);
});
