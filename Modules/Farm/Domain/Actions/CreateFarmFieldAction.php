<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Farm\Domain\DataObjects\CreateFarmFieldData;
use Modules\Farm\Models\FarmField;

/**
 * ACT-CreateFarmField (Book H2 OPS-03 §2/BR-OPS-03-018).
 */
final class CreateFarmFieldAction extends Action
{
    public function execute(CreateFarmFieldData $data): FarmField
    {
        return $this->transaction(fn (): FarmField => FarmField::create([
            'school_id' => $data->schoolId,
            'production_unit_id' => $data->productionUnitId,
            'code' => $data->code,
            'name' => $data->name,
            'area_hectares' => $data->areaHectares,
            'soil_type' => $data->soilType,
            'is_irrigated' => $data->isIrrigated,
            'irrigation_type' => $data->irrigationType,
            'water_source_id' => $data->waterSourceId,
        ]));
    }
}
