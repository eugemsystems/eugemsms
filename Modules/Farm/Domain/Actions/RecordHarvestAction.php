<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Farm\Domain\DataObjects\RecordHarvestData;
use Modules\Farm\Domain\Events\HarvestRecorded;
use Modules\Farm\Models\CropCycle;
use Modules\Farm\Models\Harvest;
use Modules\Stores\Domain\Actions\ReceiveStockAction;
use Modules\Stores\Domain\DataObjects\ReceiveStockData;
use Modules\Stores\Models\StockMovement;

/**
 * ACT-RecordHarvest (Book H2 OPS-03 §2/§3 ⭐⭐/BR-OPS-03-005/006/
 * AC-OPS-03-001). Cost per kg is the internal transfer price — total
 * cycle cost divided by cumulative actual yield, recomputed on every
 * harvest event as both grow. The lot itself is a real `FIN-09` stock
 * lot: `ReceiveStockAction` (the same shared receipt primitive
 * `FIN-08`'s GRN line and a direct stock receipt already use) posts
 * `Dr Farm Inventory / Cr Farm Production` in the farm store.
 */
final class RecordHarvestAction extends Action
{
    public function __construct(
        private readonly ReceiveStockAction $receiveStock,
    ) {}

    public function execute(RecordHarvestData $data): Harvest
    {
        $cycle = CropCycle::with('productionUnit')->findOrFail($data->cropCycleId);

        if ($cycle->productionUnit->store_id === null) {
            throw ValidationException::withMessages([
                'cropCycleId' => "Production unit {$cycle->productionUnit->name} has no farm store to harvest into.",
            ]);
        }

        return $this->transaction(function () use ($data, $cycle): Harvest {
            $newYieldKg = (float) $cycle->actual_yield_kg + $data->quantityKg;
            $costPerKgMinor = $newYieldKg > 0 ? (int) round($cycle->total_cost_minor / $newYieldKg) : 0;

            $lot = $this->receiveStock->execute(new ReceiveStockData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                storeId: $cycle->productionUnit->store_id,
                itemId: $data->itemId,
                quantity: $data->quantityKg,
                unitCostMinor: $costPerKgMinor,
                currency: $cycle->currency,
                receivedOn: $data->harvestedOn,
                performedByUserId: $data->recordedByUserId,
                contraAccountId: $data->farmProductionContraAccountId,
                sourceType: 'harvest',
            ));

            $journalId = StockMovement::where('lot_id', $lot->id)->value('journal_id');

            $harvest = Harvest::create([
                'school_id' => $data->schoolId,
                'crop_cycle_id' => $cycle->id,
                'harvested_on' => $data->harvestedOn->toDateString(),
                'quantity_kg' => $data->quantityKg,
                'quality_grade' => $data->qualityGrade,
                'moisture_percent' => $data->moisturePercent,
                'unit_cost_minor' => $costPerKgMinor,
                'currency' => $cycle->currency,
                'destination' => $data->destination,
                'store_id' => $cycle->productionUnit->store_id,
                'stock_lot_id' => $lot->id,
                'journal_id' => $journalId,
                'recorded_by' => $data->recordedByUserId,
            ]);

            $cycle->update([
                'actual_harvest_on' => $data->harvestedOn->toDateString(),
                'actual_yield_kg' => $newYieldKg,
                'cost_per_kg_minor' => $costPerKgMinor,
                'status' => 'harvested',
            ]);

            event(new HarvestRecorded($harvest));

            return $harvest;
        });
    }
}
