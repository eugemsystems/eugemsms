<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\EventAttendee;
use Modules\Comms\Models\EventRegistration;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CancelEventRegistration (Book I COM-06 §3/BR-COM-06-007). Only
 * an attendee who held a real seat (`registered`/`paid`/`checked_in`)
 * frees capacity — cancelling from `waitlisted` simply removes that
 * person from the queue and never touches `registered_count`.
 */
final class CancelEventRegistrationAction extends Action
{
    public function __construct(
        private readonly PromoteFromEventWaitlistAction $promoteFromWaitlist,
    ) {}

    public function execute(int $attendeeId): EventAttendee
    {
        $registrationId = $this->transaction(function () use ($attendeeId): int {
            $attendee = EventAttendee::findOrFail($attendeeId);
            $heldASeat = $attendee->isActive();

            $attendee->update(['status' => 'cancelled', 'waitlist_position' => null]);

            if ($heldASeat) {
                EventRegistration::where('id', $attendee->registration_id)->decrement('registered_count');
            }

            return $attendee->registration_id;
        });

        $this->promoteFromWaitlist->execute($registrationId);

        return EventAttendee::findOrFail($attendeeId);
    }
}
