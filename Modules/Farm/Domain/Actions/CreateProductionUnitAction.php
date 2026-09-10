<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Farm\Domain\DataObjects\CreateProductionUnitData;
use Modules\Farm\Models\ProductionUnit;

/**
 * ACT-CreateProductionUnit (Book H2 OPS-03 §2 ⭐/BR-OPS-03-001).
 */
final class CreateProductionUnitAction extends Action
{
    public function execute(CreateProductionUnitData $data): ProductionUnit
    {
        return $this->transaction(fn (): ProductionUnit => ProductionUnit::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'unit_type' => $data->unitType,
            'cost_centre_id' => $data->costCentreId,
            'manager_staff_id' => $data->managerStaffId,
            'store_id' => $data->storeId,
            'area_hectares' => $data->areaHectares,
            'is_active' => true,
        ]));
    }
}
