<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\Journal;
use Modules\Stores\Domain\Actions\ApprovePurchaseOrderAction;
use Modules\Stores\Domain\Actions\ApproveSupplierAction;
use Modules\Stores\Domain\Actions\CreatePurchaseOrderAction;
use Modules\Stores\Domain\Actions\CreateSupplierAction;
use Modules\Stores\Domain\Actions\RecordGoodsReceivedNoteAction;
use Modules\Stores\Domain\DataObjects\CreatePurchaseOrderData;
use Modules\Stores\Domain\DataObjects\CreateSupplierData;
use Modules\Stores\Domain\DataObjects\RecordGoodsReceivedNoteData;
use Modules\Stores\Domain\Support\CapitalisationUnits;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\FixedAsset;

/**
 * Receives `$quantity` of a capital line costing `$unitCost`, optionally tied to an asset category.
 *
 * @return array{school: School, category: AssetCategory, expense: Account, user: User}
 */
function fin10wReceiveCapitalLine(bool $withCategory, int $quantity, int $unitCost): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open']);
    $user = User::factory()->create();
    $approver = User::factory()->create();

    foreach (['journal', 'purchase_order', 'goods_received_note', 'fixed_asset'] as $type) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $type, pattern: strtoupper(substr($type, 0, 3)).'/{SEQ:6}',
        ));
    }

    $costCentre = CostCentre::factory()->for($school)->create();
    $grnAccrual = Account::factory()->for($school)->create(['code' => 'GRNACC']);
    $expense = Account::factory()->for($school)->expense()->create(['code' => 'EXP-EQUIP']);
    $control = Account::factory()->for($school)->controlAccount('supplier')->create();
    BankAccount::factory()->create(['school_id' => $school->id, 'gl_account_id' => Account::factory()->for($school)->create()->id]);

    $category = AssetCategory::factory()->create([
        'school_id' => $school->id, 'code' => 'ICT', 'asset_account_id' => Account::factory()->for($school)->create(['code' => 'ASSET-ICT'])->id,
        'accum_depreciation_account_id' => Account::factory()->for($school)->create(['code' => 'ACC-ICT'])->id,
        'depreciation_expense_account_id' => Account::factory()->for($school)->expense()->create(['code' => 'DEP-ICT'])->id,
        'disposal_account_id' => Account::factory()->for($school)->create(['code' => 'DISP-ICT'])->id,
    ]);

    $supplier = app(CreateSupplierAction::class)->execute(new CreateSupplierData(
        schoolId: $school->id, code: 'SUP-1', name: 'Bhundu Stationers', supplierType: 'company', preferredCurrency: 'USD',
        createdByUserId: $user->id, controlAccountId: $control->id,
    ));
    app(ApproveSupplierAction::class)->execute($supplier->id, $approver->id);

    $order = app(CreatePurchaseOrderAction::class)->execute(new CreatePurchaseOrderData(
        schoolId: $school->id, academicYearId: $year->id, termId: $term->id, supplierId: $supplier->id,
        costCentreId: $costCentre->id, orderDate: now(), currency: 'USD',
        lines: [['itemId' => null, 'description' => 'Projector', 'quantityOrdered' => $quantity, 'unit' => 'ea', 'unitPriceMinor' => $unitCost, 'taxRatePercent' => 0, 'taxCategory' => 'exempt', 'expenseAccountId' => $expense->id, 'isCapital' => true, 'storeId' => null, 'assetCategoryId' => $withCategory ? $category->id : null]],
        createdByUserId: $user->id,
    ));
    app(ApprovePurchaseOrderAction::class)->execute($order->id, $approver->id);

    app(RecordGoodsReceivedNoteAction::class)->execute(new RecordGoodsReceivedNoteData(
        schoolId: $school->id, termId: $term->id, purchaseOrderId: $order->id, receivedOn: now(),
        receivedByUserId: $user->id, grnAccrualAccountId: $grnAccrual->id,
        lines: [['poLineId' => $order->lines->first()->id, 'quantityDelivered' => $quantity, 'quantityAccepted' => $quantity, 'quantityRejected' => 0, 'rejectionReason' => null, 'batchNumber' => null, 'expiryDate' => null, 'unitCostMinor' => $unitCost]],
    ));

    return compact('school', 'category', 'expense', 'user');
}

it('capitalises each received unit of a capital PO line into the register and reclassifies the expense (BR-FIN-10-002)', function (): void {
    $f = fin10wReceiveCapitalLine(true, 3, 40000);

    $assets = FixedAsset::query()->orderBy('id')->get();
    expect($assets)->toHaveCount(3)
        ->and($assets->pluck('acquisition_cost_minor')->unique()->all())->toBe([40000])
        ->and($assets->first()->category_id)->toBe($f['category']->id)
        ->and($assets->first()->name)->toBe('Projector (1/3)')
        ->and($assets->first()->acquisition_source)->toBe('purchase');

    $journal = Journal::where('source_type', 'fixed_asset')->where('source_id', $assets->first()->id)->first();
    expect($journal->lines->firstWhere('direction', 'DR')->account_id)->toBe($f['category']->asset_account_id)
        ->and($journal->lines->firstWhere('direction', 'CR')->account_id)->toBe($f['expense']->id);
});

it('leaves a capital line without an asset category for manual capitalisation', function (): void {
    fin10wReceiveCapitalLine(false, 2, 40000);

    expect(FixedAsset::query()->count())->toBe(0);
});

it('splits quantities into one asset per whole unit, or a single asset when fractional', function (): void {
    expect(CapitalisationUnits::costs(3.0, 500))->toBe([500, 500, 500])
        ->and(CapitalisationUnits::costs(2.5, 400))->toBe([1000])
        ->and(CapitalisationUnits::costs(0.0, 400))->toBe([]);
});
