<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Transport\Domain\DataObjects\CreateVehicleData;
use Modules\Transport\Models\Vehicle;

/**
 * ACT-CreateVehicle (Book H2 OPS-01 §2).
 */
final class CreateVehicleAction extends Action
{
    public function execute(CreateVehicleData $data): Vehicle
    {
        return $this->transaction(fn (): Vehicle => Vehicle::create([
            'school_id' => $data->schoolId,
            'fleet_number' => $data->fleetNumber,
            'registration_number' => $data->registrationNumber,
            'vehicle_type' => $data->vehicleType,
            'make' => $data->make,
            'model' => $data->model,
            'year_of_manufacture' => $data->yearOfManufacture,
            'seating_capacity' => $data->seatingCapacity,
            'standing_capacity' => $data->standingCapacity,
            'fuel_type' => $data->fuelType,
            'tank_capacity_litres' => $data->tankCapacityLitres,
            'expected_km_per_litre' => $data->expectedKmPerLitre,
            'current_odometer_km' => 0,
            'fixed_asset_id' => $data->fixedAssetId,
            'maintenance_asset_id' => $data->maintenanceAssetId,
            'cost_centre_id' => $data->costCentreId,
            'tracker_device_id' => $data->trackerDeviceId,
            'status' => 'active',
            'is_active' => true,
        ]));
    }
}
