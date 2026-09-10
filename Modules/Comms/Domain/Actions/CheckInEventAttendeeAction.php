<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Exceptions\TicketNotPaidException;
use Modules\Comms\Models\EventAttendee;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CheckInEventAttendee (Book I COM-06 §3 ⭐/BR-COM-06-006
 * (AC-COM-06-003)). Refuses outright when the registration requires a
 * ticket and this attendee's own record hasn't reached `paid` — see
 * `ConfirmEventTicketPaymentAction`'s docblock for what "paid" means
 * here.
 */
final class CheckInEventAttendeeAction extends Action
{
    public function execute(int $attendeeId): EventAttendee
    {
        return $this->transaction(function () use ($attendeeId): EventAttendee {
            $attendee = EventAttendee::findOrFail($attendeeId);
            $registration = $attendee->registration;

            if ($registration->requires_ticket && ! $attendee->isPaid()) {
                throw TicketNotPaidException::forAttendee($attendeeId);
            }

            $attendee->update(['status' => 'checked_in', 'checked_in_at' => Carbon::now()]);

            return $attendee;
        });
    }
}
