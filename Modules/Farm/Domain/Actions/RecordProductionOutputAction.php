<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Farm\Domain\DataObjects\RecordProductionOutputData;
use Modules\Farm\Models\ProductionOutput;

/**
 * ACT-RecordProductionOutput (Book H2 OPS-03 §2/BR-OPS-03-015). Daily
 * milk/egg/meat recording per unit per day — the `UNIQUE(production_unit_id,
 * output_date, output_type)` constraint is what actually enforces
 * "per day", this is just a normal create.
 */
final class RecordProductionOutputAction extends Action
{
    public function execute(RecordProductionOutputData $data): ProductionOutput
    {
        return $this->transaction(fn (): ProductionOutput => ProductionOutput::create([
            'school_id' => $data->schoolId,
            'production_unit_id' => $data->productionUnitId,
            'output_date' => $data->outputDate->toDateString(),
            'output_type' => $data->outputType,
            'quantity' => $data->quantity,
            'unit' => $data->unit,
            'unit_cost_minor' => $data->unitCostMinor,
            'currency' => $data->currency,
            'destination' => $data->destination,
            'recorded_by' => $data->recordedByUserId,
        ]));
    }
}
