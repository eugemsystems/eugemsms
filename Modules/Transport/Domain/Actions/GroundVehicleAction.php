<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Transport\Domain\Events\VehicleGrounded;
use Modules\Transport\Models\Vehicle;

/**
 * ACT-GroundVehicle (Book H2 OPS-01 §2/BR-OPS-01-001).
 */
final class GroundVehicleAction extends Action
{
    public function execute(int $vehicleId, string $reason): Vehicle
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        return $this->transaction(function () use ($vehicle, $reason): Vehicle {
            $vehicle->update(['status' => 'grounded', 'grounded_reason' => $reason]);

            event(new VehicleGrounded($vehicle, $reason));

            return $vehicle;
        });
    }
}
