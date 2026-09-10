<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Transport\Domain\Events\LearnerNoShow;
use Modules\Transport\Domain\Events\TripDeparted;
use Modules\Transport\Models\Trip;
use Modules\Transport\Models\TripPassenger;
use Throwable;

/**
 * ACT-DepartTrip (Book H2 OPS-01 §4 ⭐/BR-OPS-01-010/AC-OPS-01-006).
 * Any passenger still `expected` at departure is a no-show — the
 * school is always alerted; the guardian is always notified
 * regardless of the boarding-notification setting, since this is the
 * opposite case (an absence, not routine confirmation).
 */
final class DepartTripAction extends Action
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(int $tripId): Trip
    {
        $trip = Trip::with('passengers')->findOrFail($tripId);

        if ($trip->status !== 'scheduled') {
            throw new InvalidStateTransitionException(
                "Trip #{$trip->id} must be scheduled to depart (currently {$trip->status}).",
                ['trip_id' => $trip->id, 'status' => $trip->status],
            );
        }

        return $this->transaction(function () use ($trip): Trip {
            $noShows = $trip->passengers->where('status', 'expected');

            foreach ($noShows as $passenger) {
                $passenger->update(['status' => 'no_show']);
                event(new LearnerNoShow($passenger));
                $this->notifyGuardianOfNoShow($passenger);
            }

            $trip->update(['status' => 'departed', 'departed_at' => Carbon::now()]);

            event(new TripDeparted($trip));

            return $trip;
        });
    }

    private function notifyGuardianOfNoShow(TripPassenger $passenger): void
    {
        if ($passenger->student_id === null) {
            return;
        }

        $student = Student::find($passenger->student_id);

        if ($student === null) {
            return;
        }

        $link = StudentGuardian::query()
            ->where('student_id', $student->id)
            ->where('is_primary_contact', true)
            ->where('status', 'active')
            ->with('guardian')
            ->first();

        if ($link === null || $link->guardian === null) {
            return;
        }

        $guardian = $link->guardian;

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $passenger->school_id,
                notificationKey: 'transport.learner_no_show',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                context: ['student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name]],
                recipientId: $guardian->id,
                relatedType: 'trip_passenger',
                relatedId: $passenger->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // Non-blocking.
        }
    }
}
