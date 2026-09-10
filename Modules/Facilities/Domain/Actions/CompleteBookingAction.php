<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Facilities\Models\ResourceBooking;

/**
 * ACT-CompleteBooking (Book H2 OPS-05 §3/BR-OPS-05-005/006). Records
 * the resource's condition on handback — the fact
 * `AssessDamageAndRefundDepositAction` checks before releasing a
 * deposit.
 */
final class CompleteBookingAction extends Action
{
    public function execute(int $bookingId, ?string $conditionAfterNotes = null): ResourceBooking
    {
        $booking = ResourceBooking::findOrFail($bookingId);

        if (! in_array($booking->status, ['confirmed', 'in_progress'], true)) {
            throw new InvalidStateTransitionException(
                "Booking #{$booking->id} must be confirmed or in progress to complete (currently {$booking->status}).",
                ['booking_id' => $booking->id, 'status' => $booking->status],
            );
        }

        return $this->transaction(fn (): ResourceBooking => tap($booking)->update([
            'status' => 'completed',
            'condition_after_notes' => $conditionAfterNotes,
        ]));
    }
}
