<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\ConsultationBooking;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CancelConsultationBooking (Book I COM-07 §3/BR-COM-07-005).
 * Flipping `status` away from `booked` is itself what "releases the
 * slot back to the booking pool" means under the 3-column unique
 * index (see `consultation_bookings`' own migration docblock) — no
 * separate release step is needed.
 */
final class CancelConsultationBookingAction extends Action
{
    public function execute(int $bookingId): ConsultationBooking
    {
        return $this->transaction(function () use ($bookingId): ConsultationBooking {
            $booking = ConsultationBooking::findOrFail($bookingId);
            $booking->update(['status' => 'cancelled']);

            return $booking;
        });
    }
}
