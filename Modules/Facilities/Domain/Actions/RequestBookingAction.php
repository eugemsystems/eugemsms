<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Facilities\Domain\DataObjects\RequestBookingData;
use Modules\Facilities\Domain\Exceptions\ResourceNotAvailableException;
use Modules\Facilities\Models\BookableResource;
use Modules\Facilities\Models\ResourceBooking;

/**
 * ACT-RequestBooking (Book H2 OPS-05 §3/BR-OPS-05-001/002/003/
 * AC-OPS-05-001/002). Refuses outright on any clash, naming it —
 * `CheckResourceAvailabilityAction` is the single source of truth for
 * both booking-vs-booking and booking-vs-timetable clashes. An
 * external booking starts `requested`, awaiting
 * `ApproveExternalHireAction`; an internal one, with no contract/
 * deposit gate to clear, starts `approved` directly.
 */
final class RequestBookingAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly CheckResourceAvailabilityAction $checkAvailability,
    ) {}

    public function execute(RequestBookingData $data): ResourceBooking
    {
        $resource = BookableResource::findOrFail($data->resourceId);

        $setupFrom = $data->startsAt->copy()->subMinutes($resource->requires_setup_minutes);
        $cleanupUntil = $data->endsAt->copy()->addMinutes($resource->requires_cleaning_minutes);

        $reason = $this->checkAvailability->execute($resource->id, $data->startsAt, $data->endsAt);

        if ($reason !== null) {
            throw ResourceNotAvailableException::forReason($resource->id, $reason);
        }

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'resource_booking',
            allocatedByUserId: $data->requestedByUserId,
            termId: $data->termId,
        ));

        return $this->transaction(fn (): ResourceBooking => ResourceBooking::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'booking_number' => $number->formatted_number,
            'resource_id' => $resource->id,
            'booking_type' => $data->bookingType,
            'purpose' => $data->purpose,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'setup_from' => $setupFrom,
            'cleanup_until' => $cleanupUntil,
            'expected_attendance' => $data->expectedAttendance,
            'requested_by_staff_id' => $data->requestedByStaffId,
            'department_id' => $data->departmentId,
            'hirer_name' => $data->hirerName,
            'hirer_contact' => $data->hirerContact,
            'hirer_organisation' => $data->hirerOrganisation,
            'hire_amount_minor' => $data->hireAmountMinor,
            'deposit_amount_minor' => $data->depositAmountMinor,
            'status' => $data->bookingType === 'external' ? 'requested' : 'approved',
            'recurrence_rule' => $data->recurrenceRule,
            'parent_booking_id' => $data->parentBookingId,
        ]));
    }
}
