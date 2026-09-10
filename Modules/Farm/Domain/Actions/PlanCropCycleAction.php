<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Farm\Domain\DataObjects\PlanCropCycleData;
use Modules\Farm\Domain\Events\CropCyclePlanted;
use Modules\Farm\Models\CropCycle;

/**
 * ACT-PlanCropCycle (Book H2 OPS-03 §2).
 */
final class PlanCropCycleAction extends Action
{
    public function execute(PlanCropCycleData $data): CropCycle
    {
        return $this->transaction(function () use ($data): CropCycle {
            $cycle = CropCycle::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'production_unit_id' => $data->productionUnitId,
                'field_id' => $data->fieldId,
                'cycle_reference' => $data->cycleReference,
                'crop' => $data->crop,
                'variety' => $data->variety,
                'season' => $data->season,
                'area_planted_hectares' => $data->areaPlantedHectares,
                'planted_on' => $data->plantedOn?->toDateString(),
                'expected_harvest_on' => $data->expectedHarvestOn?->toDateString(),
                'expected_yield_kg' => $data->expectedYieldKg,
                'input_cost_minor' => 0,
                'labour_cost_minor' => 0,
                'overhead_cost_minor' => 0,
                'total_cost_minor' => 0,
                'currency' => $data->currency,
                'status' => 'planned',
            ]);

            event(new CropCyclePlanted($cycle));

            return $cycle;
        });
    }
}
