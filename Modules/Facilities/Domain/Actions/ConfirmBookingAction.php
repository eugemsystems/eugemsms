<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\School;
use Modules\Facilities\Domain\DataObjects\ConfirmBookingData;
use Modules\Facilities\Models\ResourceBooking;
use Modules\Operations\Domain\Actions\CreateWorkOrderAction;
use Modules\Operations\Domain\DataObjects\CreateWorkOrderData;

/**
 * ACT-ConfirmBooking (Book H2 OPS-05 §3 ⭐/BR-OPS-05-003/007). An
 * external hire needs a signed contract and a received deposit before
 * it confirms — both hard requirements, not warnings. A confirmed
 * booking that needs setup and/or cleaning time generates real
 * `OPS-02` work orders for it, through the same `CreateWorkOrderAction`
 * every other module in this book uses.
 */
final class ConfirmBookingAction extends Action
{
    public function __construct(
        private readonly CreateWorkOrderAction $createWorkOrder,
    ) {}

    public function execute(int $bookingId, ConfirmBookingData $data): ResourceBooking
    {
        $booking = ResourceBooking::with('resource')->findOrFail($bookingId);
        $resource = $booking->resource;

        if ($booking->status !== 'approved') {
            throw new InvalidStateTransitionException(
                "Booking #{$booking->id} must be approved to confirm (currently {$booking->status}).",
                ['booking_id' => $booking->id, 'status' => $booking->status],
            );
        }

        $contractFileId = $data->contractFileId ?? $booking->contract_file_id;

        if ($booking->booking_type === 'external') {
            if ($contractFileId === null) {
                throw ValidationException::withMessages([
                    'contractFileId' => 'A signed contract is required before an external hire can be confirmed (BR-OPS-05-003).',
                ]);
            }

            if ($booking->deposit_amount_minor === null || $booking->deposit_amount_minor <= 0) {
                throw ValidationException::withMessages([
                    'deposit' => 'A deposit is required before an external hire can be confirmed (BR-OPS-05-003).',
                ]);
            }
        }

        return $this->transaction(function () use ($booking, $resource, $data, $contractFileId): ResourceBooking {
            $setupWorkOrderId = null;
            $cleanupWorkOrderId = null;
            $currency = School::findOrFail($booking->school_id)->base_currency;

            if ($resource->requires_setup_minutes > 0) {
                $setupOrder = $this->createWorkOrder->execute(new CreateWorkOrderData(
                    schoolId: $booking->school_id,
                    academicYearId: $data->academicYearId,
                    termId: $booking->term_id,
                    workType: 'other',
                    title: "Setup — {$resource->name} for booking {$booking->booking_number}",
                    description: "Set up {$resource->name} for: {$booking->purpose}.",
                    priority: 'normal',
                    assignedTeam: 'in_house',
                    costCentreId: $resource->cost_centre_id,
                    currency: $currency,
                    raisedByUserId: $data->confirmedByUserId,
                    scheduledFor: $booking->setup_from,
                    targetCompletion: $booking->starts_at,
                ));
                $setupWorkOrderId = $setupOrder->id;
            }

            if ($resource->requires_cleaning_minutes > 0) {
                $cleanupOrder = $this->createWorkOrder->execute(new CreateWorkOrderData(
                    schoolId: $booking->school_id,
                    academicYearId: $data->academicYearId,
                    termId: $booking->term_id,
                    workType: 'other',
                    title: "Cleanup — {$resource->name} after booking {$booking->booking_number}",
                    description: "Clean {$resource->name} after: {$booking->purpose}.",
                    priority: 'normal',
                    assignedTeam: 'in_house',
                    costCentreId: $resource->cost_centre_id,
                    currency: $currency,
                    raisedByUserId: $data->confirmedByUserId,
                    scheduledFor: $booking->ends_at,
                    targetCompletion: $booking->cleanup_until,
                ));
                $cleanupWorkOrderId = $cleanupOrder->id;
            }

            $booking->update([
                'status' => 'confirmed',
                'contract_file_id' => $contractFileId,
                'setup_work_order_id' => $setupWorkOrderId,
                'cleanup_work_order_id' => $cleanupWorkOrderId,
            ]);

            return $booking;
        });
    }
}
