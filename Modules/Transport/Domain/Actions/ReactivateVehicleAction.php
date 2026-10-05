<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Transport\Models\Vehicle;

/**
 * ACT-ReactivateVehicle (Book H2 OPS-01 §2/BR-OPS-01-001). Admin-UI-pass
 * gap-fill — `GroundVehicleAction` has no reverse anywhere in the
 * shipped domain layer, which would leave the Compliance monitor
 * screen's own "grounded vehicles" list a one-way trip once a
 * certificate is renewed. Narrow and forward-only in the other
 * direction: only a `grounded` vehicle may return to `active`, and
 * `ScheduleTripAction`'s own expired-compliance check still runs
 * independently on the next trip regardless of this flag.
 */
final class ReactivateVehicleAction extends Action
{
    public function execute(int $vehicleId): Vehicle
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        if ($vehicle->status !== 'grounded') {
            throw new InvalidStateTransitionException(
                "Vehicle #{$vehicle->id} is not grounded (currently {$vehicle->status}).",
                ['vehicle_id' => $vehicle->id, 'status' => $vehicle->status],
            );
        }

        return $this->transaction(fn (): Vehicle => tap($vehicle)->update([
            'status' => 'active',
            'grounded_reason' => null,
        ]));
    }
}
