<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Utilities\Domain\DataObjects\CreateSolarInstallationData;
use Modules\Utilities\Models\SolarInstallation;

/**
 * ACT-CreateSolarInstallation (Book H2 OPS-04 §2).
 */
final class CreateSolarInstallationAction extends Action
{
    public function execute(CreateSolarInstallationData $data): SolarInstallation
    {
        return $this->transaction(fn (): SolarInstallation => SolarInstallation::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'capacity_kwp' => $data->capacityKwp,
            'battery_capacity_kwh' => $data->batteryCapacityKwh,
            'serves_scope' => $data->servesScope,
            'scope_id' => $data->scopeId,
            'commissioned_on' => $data->commissionedOn?->toDateString(),
            'fixed_asset_id' => $data->fixedAssetId,
            'maintenance_asset_id' => $data->maintenanceAssetId,
            'status' => 'operational',
        ]));
    }
}
