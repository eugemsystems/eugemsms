<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Operations\Domain\DataObjects\CreateMaintenanceAssetData;
use Modules\Operations\Models\MaintenanceAsset;

/**
 * ACT-CreateMaintenanceAsset (Book H2 OPS-02 §2). Admin-UI-pass
 * gap-fill — the domain layer shipped `maintenance_assets`' own
 * migration, model and factory, but no Action anywhere ever created a
 * row from one (verified: every existing row in this codebase's own
 * tests came from `MaintenanceAssetFactory` directly, the same gap
 * prior books' own `CreateHostelWingAction`/`CreateCurriculumFrameworkAction`
 * closed). Create-only, mirroring that precedent — no
 * `UpdateMaintenanceAssetAction` exists either.
 */
final class CreateMaintenanceAssetAction extends Action
{
    public function execute(CreateMaintenanceAssetData $data): MaintenanceAsset
    {
        return $this->transaction(fn (): MaintenanceAsset => MaintenanceAsset::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'asset_type' => $data->assetType,
            'fixed_asset_id' => $data->fixedAssetId,
            'vehicle_id' => $data->vehicleId,
            'location' => $data->location,
            'building' => $data->building,
            'cost_centre_id' => $data->costCentreId,
            'criticality' => $data->criticality,
            'condition' => $data->condition,
            'service_interval_days' => $data->serviceIntervalDays,
            'service_interval_units' => $data->serviceIntervalUnits,
            'is_active' => true,
        ]));
    }
}
