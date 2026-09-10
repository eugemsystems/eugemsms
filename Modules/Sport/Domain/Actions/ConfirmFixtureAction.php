<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Facilities\Domain\Actions\RequestBookingAction;
use Modules\Facilities\Domain\DataObjects\RequestBookingData;
use Modules\Sport\Domain\DataObjects\ConfirmFixtureData;
use Modules\Sport\Models\Fixture;
use Modules\Transport\Domain\Actions\ScheduleTripAction;
use Modules\Transport\Domain\DataObjects\ScheduleTripData;
use Modules\Transport\Models\TripPassenger;

/**
 * ACT-ConfirmFixture (Book H2 OPS-07 §3 ⭐/BR-OPS-07-006/AC-OPS-07-002).
 * An away fixture creates a real `OPS-01` trip
 * (`Modules\Transport\Domain\Actions\ScheduleTripAction`, `tripType:
 * 'fixture'` — a non-`route` type, so its own manifest auto-generation
 * is skipped, and this action inserts the squad as `TripPassenger`
 * rows itself in that same shape). A home fixture creates a real
 * `OPS-05` internal venue booking
 * (`Modules\Facilities\Domain\Actions\RequestBookingAction`). Neither
 * requires a squad already selected — an empty squad still produces a
 * trip/booking, just with no passengers yet.
 */
final class ConfirmFixtureAction extends Action
{
    public function __construct(
        private readonly ScheduleTripAction $scheduleTrip,
        private readonly RequestBookingAction $requestBooking,
    ) {}

    public function execute(ConfirmFixtureData $data): Fixture
    {
        $fixture = Fixture::findOrFail($data->fixtureId);

        return $this->transaction(function () use ($fixture, $data): Fixture {
            if ($fixture->venue_type === 'away' && $data->vehicleId !== null && $data->driverId !== null) {
                $trip = $this->scheduleTrip->execute(new ScheduleTripData(
                    schoolId: $fixture->school_id,
                    termId: $fixture->term_id,
                    tripDate: $fixture->fixture_date,
                    tripType: 'fixture',
                    vehicleId: $data->vehicleId,
                    driverId: $data->driverId,
                    escortStaffId: $data->escortStaffId,
                    purpose: "Fixture vs {$fixture->opponent}",
                    destination: $fixture->venue_name,
                    sourceType: 'fixture',
                    sourceId: $fixture->id,
                ));

                foreach ($fixture->squad_student_ids ?? [] as $studentId) {
                    TripPassenger::create([
                        'school_id' => $fixture->school_id,
                        'trip_id' => $trip->id,
                        'student_id' => $studentId,
                        'status' => 'expected',
                    ]);
                }

                $trip->update(['passenger_count' => count($fixture->squad_student_ids ?? [])]);

                $fixture->update(['trip_id' => $trip->id]);
            }

            if ($fixture->venue_type === 'home' && $data->resourceId !== null) {
                $startsAt = $this->resolveStartsAt($fixture);

                $booking = $this->requestBooking->execute(new RequestBookingData(
                    schoolId: $fixture->school_id,
                    termId: $fixture->term_id,
                    resourceId: $data->resourceId,
                    bookingType: 'internal',
                    purpose: "Fixture vs {$fixture->opponent}",
                    startsAt: $startsAt,
                    endsAt: $startsAt->copy()->addHours(2),
                    requestedByUserId: $data->confirmedByUserId,
                ));

                $fixture->update(['booking_id' => $booking->id]);
            }

            $fixture->update(['status' => 'confirmed']);

            return $fixture->fresh();
        });
    }

    private function resolveStartsAt(Fixture $fixture): Carbon
    {
        $date = $fixture->fixture_date->toDateString();
        $time = $fixture->start_time ?? '12:00:00';

        return Carbon::parse("{$date} {$time}");
    }
}
