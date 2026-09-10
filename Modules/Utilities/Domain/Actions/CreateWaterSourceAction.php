<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Utilities\Domain\DataObjects\CreateWaterSourceData;
use Modules\Utilities\Models\WaterSource;

/**
 * ACT-CreateWaterSource (Book H2 OPS-04 §2).
 */
final class CreateWaterSourceAction extends Action
{
    public function execute(CreateWaterSourceData $data): WaterSource
    {
        return $this->transaction(fn (): WaterSource => WaterSource::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'source_type' => $data->sourceType,
            'depth_metres' => $data->depthMetres,
            'yield_litres_per_hour' => $data->yieldLitresPerHour,
            'pump_capacity' => $data->pumpCapacity,
            'storage_capacity_litres' => $data->storageCapacityLitres,
            'maintenance_asset_id' => $data->maintenanceAssetId,
            'status' => 'operational',
        ]));
    }
}
