<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Facilities\Models\ResourceBooking;

/**
 * ACT-ApproveExternalHire (Book H2 OPS-05 §3/BR-OPS-05-003). `CORE-07`'s
 * real multi-step chain isn't wired into any domain module yet
 * anywhere in this codebase — this is the same single-gate boundary
 * every other module's own approval action already uses.
 */
final class ApproveExternalHireAction extends Action
{
    public function execute(int $bookingId, int $approvedByUserId): ResourceBooking
    {
        $booking = ResourceBooking::findOrFail($bookingId);

        if ($booking->booking_type !== 'external' || $booking->status !== 'requested') {
            throw new InvalidStateTransitionException(
                "Booking #{$booking->id} must be an external booking still requested to approve (currently {$booking->status}).",
                ['booking_id' => $booking->id, 'booking_type' => $booking->booking_type, 'status' => $booking->status],
            );
        }

        return $this->transaction(fn (): ResourceBooking => tap($booking)->update([
            'status' => 'approved',
            'approval_request_id' => $approvedByUserId,
        ]));
    }
}
