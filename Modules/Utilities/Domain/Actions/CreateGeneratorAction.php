<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Utilities\Domain\DataObjects\CreateGeneratorData;
use Modules\Utilities\Models\Generator;

/**
 * ACT-CreateGenerator (Book H2 OPS-04 §2).
 */
final class CreateGeneratorAction extends Action
{
    public function execute(CreateGeneratorData $data): Generator
    {
        return $this->transaction(fn (): Generator => Generator::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'capacity_kva' => $data->capacityKva,
            'fuel_type' => $data->fuelType,
            'tank_capacity_litres' => $data->tankCapacityLitres,
            'expected_litres_per_hour' => $data->expectedLitresPerHour,
            'serves_scope' => $data->servesScope,
            'scope_id' => $data->scopeId,
            'current_hours' => 0,
            'fixed_asset_id' => $data->fixedAssetId,
            'maintenance_asset_id' => $data->maintenanceAssetId,
            'cost_centre_id' => $data->costCentreId,
            'status' => 'standby',
        ]));
    }
}
