<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\EventAttendee;
use Modules\Comms\Models\EventRegistration;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Guardian;
use Throwable;

/**
 * ACT-PromoteFromEventWaitlist (Book I COM-06 §3/BR-COM-06-007). The
 * capacity-freed trigger BRD-01's own `ReevaluateWaitingListAction`
 * defers ("no notification infrastructure wired to this module yet"
 * — its own docblock) is real here, since `CORE-09`/`COM-01` are both
 * fully built by this point in the book: promotion AND the offer
 * notification happen in the same call, mirroring
 * `Modules\Boarding\Domain\Actions\OpenMissingLearnerIncidentAction`'s
 * `try { dispatch } catch (Throwable) {}` guard — a notification
 * failure never blocks the promotion itself.
 */
final class PromoteFromEventWaitlistAction extends Action
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(int $registrationId): ?EventAttendee
    {
        return $this->transaction(function () use ($registrationId): ?EventAttendee {
            $registration = EventRegistration::where('id', $registrationId)->lockForUpdate()->firstOrFail();

            if ($registration->isFull()) {
                return null;
            }

            $next = EventAttendee::where('registration_id', $registration->id)
                ->where('status', 'waitlisted')
                ->orderBy('waitlist_position')
                ->first();

            if ($next === null) {
                return null;
            }

            $next->update(['status' => 'registered', 'waitlist_position' => null]);
            $registration->increment('registered_count');

            $this->notifyPromoted($registration, $next);

            return $next;
        });
    }

    private function notifyPromoted(EventRegistration $registration, EventAttendee $attendee): void
    {
        if ($attendee->guardian_id === null) {
            return;
        }

        $guardian = Guardian::where('school_id', $attendee->school_id)->find($attendee->guardian_id);

        if ($guardian === null || $guardian->user_id === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $attendee->school_id,
                notificationKey: 'comms.event_waitlist_promoted',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                context: ['event' => ['title' => $registration->calendarEvent->title]],
                recipientId: $guardian->id,
                relatedType: 'event_attendee',
                relatedId: $attendee->id,
            ));
        } catch (Throwable) {
            // A notification-dispatch failure never blocks the promotion itself.
        }
    }
}
