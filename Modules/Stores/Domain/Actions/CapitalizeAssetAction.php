<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\DataObjects\CapitalizeAssetData;
use Modules\Stores\Domain\Events\AssetCapitalised;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\FixedAsset;

/**
 * ACT-CapitalizeAsset (Book H1 FIN-10 §6 ⭐⭐/BR-FIN-10-001/002/003/
 * AC-FIN-10-003/004). The single entry point every capitalisation
 * source funnels through — a real `FIN-08` capital PO line received
 * (`CapitalPurchaseReceived`), a real `FIN-09` item crossing its
 * capitalisation threshold on issue (`ItemCapitalisationDue`), a
 * direct purchase, or a donation (BR-FIN-10-003, `contraAccountId`
 * pointing at a donation income account instead of a payable/inventory
 * one) — all post `Dr Fixed Assets / Cr <contra>` through here, and
 * all get the same gapless, physically-applied asset tag
 * (BR-FIN-10-001).
 */
final class CapitalizeAssetAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(CapitalizeAssetData $data): FixedAsset
    {
        $category = AssetCategory::findOrFail($data->categoryId);
        $currency = Currency::from($data->currency);

        $depreciationMethod = $data->depreciationMethod ?? $category->default_method;
        $usefulLifeYears = $data->usefulLifeYears ?? $category->default_useful_life_years;
        $residualValueMinor = $data->residualValueMinor ?? (int) round($data->acquisitionCostMinor * (float) $category->default_residual_percent / 100);
        $isDepreciable = $category->is_depreciable && $depreciationMethod !== 'none';

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'fixed_asset',
            allocatedByUserId: $data->performedByUserId,
        ));

        return $this->transaction(function () use ($data, $category, $currency, $number, $depreciationMethod, $usefulLifeYears, $residualValueMinor, $isDepreciable): FixedAsset {
            $depreciationStartDate = $isDepreciable
                ? Carbon::parse($data->acquisitionDate->toDateString())->startOfMonth()->addMonthNoOverflow()
                : null;

            $asset = FixedAsset::create([
                'school_id' => $data->schoolId,
                'asset_tag' => $number->formatted_number,
                'category_id' => $category->id,
                'name' => $data->name,
                'acquisition_date' => $data->acquisitionDate->toDateString(),
                'acquisition_cost_minor' => $data->acquisitionCostMinor,
                'currency' => $data->currency,
                'base_cost_minor' => $data->acquisitionCostMinor,
                'acquisition_source' => $data->acquisitionSource,
                'supplier_id' => $data->supplierId,
                'purchase_order_id' => $data->purchaseOrderId,
                'grn_id' => $data->grnId,
                'stock_movement_id' => $data->stockMovementId,
                'donor_name' => $data->donorName,
                'is_depreciable' => $isDepreciable,
                'depreciation_method' => $depreciationMethod,
                'useful_life_years' => $usefulLifeYears,
                'residual_value_minor' => $residualValueMinor,
                'depreciation_start_date' => $depreciationStartDate?->toDateString(),
                'total_units_expected' => $data->totalUnitsExpected,
                'accumulated_depreciation_minor' => 0,
                'net_book_value_minor' => $data->acquisitionCostMinor,
                'fully_depreciated' => false,
                'department_id' => $data->departmentId,
                'cost_centre_id' => $data->costCentreId,
                'custodian_staff_id' => $data->custodianStaffId,
                'status' => 'active',
                'condition' => 'good',
                'next_verification_on' => Carbon::parse($data->acquisitionDate->toDateString())->addMonths($category->verification_frequency_months)->toDateString(),
                'created_by' => $data->performedByUserId,
            ]);

            $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'ASSET_CAPITALISATION',
                narration: "Asset capitalised — {$data->name} ({$number->formatted_number})",
                lines: [
                    new JournalLineData(accountId: $category->asset_account_id, direction: 'DR', amount: Money::of($data->acquisitionCostMinor, $currency), costCentreId: $data->costCentreId),
                    new JournalLineData(accountId: $data->contraAccountId, direction: 'CR', amount: Money::of($data->acquisitionCostMinor, $currency)),
                ],
                effectiveAt: $data->acquisitionDate,
                postedByUserId: $data->performedByUserId,
                sourceType: 'fixed_asset',
                sourceId: $asset->id,
            ));

            event(new AssetCapitalised($asset));

            return $asset;
        });
    }
}
