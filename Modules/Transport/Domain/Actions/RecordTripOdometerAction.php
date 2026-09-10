<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Operations\Domain\Actions\CheckUsageBasedMaintenanceAction;
use Modules\Transport\Domain\Events\OdometerRecorded;
use Modules\Transport\Models\Trip;
use Modules\Transport\Models\Vehicle;

/**
 * ACT-RecordTripOdometer (Book H2 OPS-01 §4 ⭐/BR-OPS-01-011/012/
 * AC-OPS-01-005). Distance is always computed from odometer readings,
 * never accepted as direct input. On the return reading, the delta
 * advances `vehicles.current_odometer_km` for real and triggers
 * `OPS-02`'s usage-based schedule check directly
 * (`CheckUsageBasedMaintenanceAction`) when the vehicle has a linked
 * `maintenance_asset_id` — see `OdometerRecorded`'s own docblock for
 * the one piece of this (`FIN-10` units-of-production) that stays a
 * documented deferral instead.
 */
final class RecordTripOdometerAction extends Action
{
    public function __construct(
        private readonly CheckUsageBasedMaintenanceAction $checkUsageBasedMaintenance,
    ) {}

    public function execute(int $tripId, string $reading, float $odometerKm, int $recordedByUserId): Trip
    {
        $trip = Trip::findOrFail($tripId);
        $vehicle = Vehicle::findOrFail($trip->vehicle_id);

        if ($reading === 'departure') {
            if ($odometerKm < (float) $vehicle->current_odometer_km) {
                throw ValidationException::withMessages([
                    'odometerKm' => 'Departure odometer cannot be lower than the vehicle\'s last recorded reading.',
                ]);
            }

            return $this->transaction(fn (): Trip => tap($trip)->update(['departure_odometer' => $odometerKm]));
        }

        if ($trip->departure_odometer === null) {
            throw ValidationException::withMessages([
                'odometerKm' => 'A departure odometer reading must be recorded before a return reading.',
            ]);
        }

        if ($odometerKm < (float) $trip->departure_odometer) {
            throw ValidationException::withMessages([
                'odometerKm' => 'Return odometer cannot be lower than the departure reading.',
            ]);
        }

        $distanceKm = $odometerKm - (float) $trip->departure_odometer;

        return $this->transaction(function () use ($trip, $vehicle, $odometerKm, $distanceKm, $recordedByUserId): Trip {
            $trip->update([
                'return_odometer' => $odometerKm,
                'distance_km' => $distanceKm,
                'returned_at' => Carbon::now(),
                'status' => 'completed',
            ]);

            $vehicle->update(['current_odometer_km' => $odometerKm]);

            event(new OdometerRecorded($vehicle, $distanceKm));

            if ($vehicle->maintenance_asset_id !== null) {
                $this->checkUsageBasedMaintenance->execute($vehicle->maintenance_asset_id, $odometerKm, $recordedByUserId);
            }

            return $trip;
        });
    }
}
