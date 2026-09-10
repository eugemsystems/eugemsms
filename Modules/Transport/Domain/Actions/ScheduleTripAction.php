<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Transport\Domain\DataObjects\ScheduleTripData;
use Modules\Transport\Domain\Exceptions\VehicleNotTripReadyException;
use Modules\Transport\Models\Driver;
use Modules\Transport\Models\LearnerTransport;
use Modules\Transport\Models\Trip;
use Modules\Transport\Models\TripPassenger;
use Modules\Transport\Models\Vehicle;
use Modules\Transport\Models\VehicleCompliance;

/**
 * ACT-ScheduleTrip (Book H2 OPS-01 §4 ⭐/BR-OPS-01-001/004/005/009/
 * AC-OPS-01-001). Refuses outright on any expired vehicle compliance
 * or driver document, naming the specific item. A `route`-type trip
 * auto-generates its manifest from every currently active
 * `learner_transport` assignment on that route — `trips` carries no
 * direction of its own in this schema (only `routes.direction` does),
 * so the manifest includes the route's full active roster rather than
 * filtering by a trip-level direction that has nowhere to live.
 */
final class ScheduleTripAction extends Action
{
    public function execute(ScheduleTripData $data): Trip
    {
        $vehicle = Vehicle::findOrFail($data->vehicleId);
        $driver = Driver::findOrFail($data->driverId);

        $expiredCompliance = VehicleCompliance::where('vehicle_id', $vehicle->id)
            ->whereDate('expires_on', '<', Carbon::now()->toDateString())
            ->first();

        if ($expiredCompliance !== null) {
            throw VehicleNotTripReadyException::expiredCompliance($vehicle->id, $expiredCompliance->compliance_type);
        }

        if ($driver->hasExpiredDocuments()) {
            $documentType = match (true) {
                $driver->licence_expires_on->isPast() => 'licence',
                $driver->medical_expires_on?->isPast() === true => 'medical_certificate',
                default => 'defensive_driving_cert',
            };

            throw VehicleNotTripReadyException::expiredDriverDocument($driver->id, $documentType);
        }

        return $this->transaction(function () use ($data, $vehicle): Trip {
            $trip = Trip::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'trip_date' => $data->tripDate->toDateString(),
                'trip_type' => $data->tripType,
                'route_id' => $data->routeId,
                'vehicle_id' => $data->vehicleId,
                'driver_id' => $data->driverId,
                'escort_staff_id' => $data->escortStaffId,
                'purpose' => $data->purpose,
                'destination' => $data->destination,
                'status' => 'scheduled',
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
            ]);

            if ($data->tripType === 'route' && $data->routeId !== null) {
                $this->generateManifest($trip, $vehicle);
            }

            return $trip;
        });
    }

    private function generateManifest(Trip $trip, Vehicle $vehicle): void
    {
        $roster = LearnerTransport::where('route_id', $trip->route_id)
            ->where('status', 'active')
            ->get();

        $capacity = $vehicle->seating_capacity + $vehicle->standing_capacity;

        if ($roster->count() > $capacity) {
            throw ValidationException::withMessages([
                'vehicleId' => "Route roster of {$roster->count()} exceeds this vehicle's capacity of {$capacity} (BR-OPS-01-005).",
            ]);
        }

        foreach ($roster as $assignment) {
            TripPassenger::create([
                'school_id' => $trip->school_id,
                'trip_id' => $trip->id,
                'student_id' => $assignment->student_id,
                'stop_id' => $assignment->pickup_stop_id,
                'status' => 'expected',
            ]);
        }

        $trip->update(['passenger_count' => $roster->count()]);
    }
}
