<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\School;
use Modules\Farm\Domain\DataObjects\ProductionUnitProfitabilityResult;
use Modules\Farm\Models\CropCycle;
use Modules\Farm\Models\FarmSale;
use Modules\Farm\Models\InternalTransfer;
use Modules\Farm\Models\ProductionUnit;

/**
 * ACT-ComputeProfitability (Book H2 OPS-03 §4/BR-OPS-03-017). Total
 * cost, kitchen transfer value (at internal cost), external sales,
 * and the net position they imply — per production unit, per term.
 */
final class ComputeProfitabilityAction extends Action
{
    public function execute(int $productionUnitId, int $termId, CarbonInterface $periodStart, CarbonInterface $periodEnd): ProductionUnitProfitabilityResult
    {
        $unit = ProductionUnit::findOrFail($productionUnitId);

        $totalCropCostMinor = (int) CropCycle::where('production_unit_id', $unit->id)
            ->whereBetween('actual_harvest_on', [$periodStart, $periodEnd])
            ->sum('total_cost_minor');

        $kitchenTransferValueMinor = (int) InternalTransfer::where('production_unit_id', $unit->id)
            ->where('term_id', $termId)
            ->whereBetween('transfer_date', [$periodStart, $periodEnd])
            ->sum('total_cost_minor');

        $externalSalesMinor = (int) FarmSale::where('production_unit_id', $unit->id)
            ->where('term_id', $termId)
            ->whereBetween('sale_date', [$periodStart, $periodEnd])
            ->sum('total_minor');

        return new ProductionUnitProfitabilityResult(
            productionUnitId: $unit->id,
            totalCropCostMinor: $totalCropCostMinor,
            kitchenTransferValueMinor: $kitchenTransferValueMinor,
            externalSalesMinor: $externalSalesMinor,
            currency: School::findOrFail($unit->school_id)->base_currency,
        );
    }
}
