<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Facilities\Models\ResourceBooking;

/**
 * ACT-CancelBooking (Book H2 OPS-05 §3/BR-OPS-05-008). A recurring
 * booking's own individual instances (`parent_booking_id`) each
 * cancel independently — cancelling a parent never cascades to its
 * children automatically.
 */
final class CancelBookingAction extends Action
{
    public function execute(int $bookingId, string $reason): ResourceBooking
    {
        $booking = ResourceBooking::findOrFail($bookingId);

        if (in_array($booking->status, ['completed', 'cancelled', 'rejected'], true)) {
            throw new InvalidStateTransitionException(
                "Booking #{$booking->id} cannot be cancelled from its current status ({$booking->status}).",
                ['booking_id' => $booking->id, 'status' => $booking->status],
            );
        }

        return $this->transaction(fn (): ResourceBooking => tap($booking)->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]));
    }
}
