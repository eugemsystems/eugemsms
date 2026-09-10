<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Actions;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Facilities\Domain\DataObjects\RequestBookingData;
use Modules\Facilities\Models\ResourceBooking;

/**
 * ACT-ExpandRecurringBooking (Book H2 OPS-05 §3/BR-OPS-05-008). Each
 * instance is created through `RequestBookingAction` on its own —
 * clash-checked independently and, per the rule, cancellable
 * independently via its own `parent_booking_id` link back to the
 * first. A deliberate simplification: this supports daily/weekly
 * repeats for a fixed occurrence count rather than parsing an
 * arbitrary iCal RRULE string — no RRULE parser exists anywhere in
 * this codebase's dependencies, and `recurrence_rule` itself stays a
 * free-text record of what was requested regardless.
 */
final class ExpandRecurringBookingAction extends Action
{
    public function __construct(
        private readonly RequestBookingAction $requestBooking,
    ) {}

    /**
     * @return Collection<int, ResourceBooking>
     */
    public function execute(int $parentBookingId, string $frequency, int $occurrences, int $requestedByUserId): Collection
    {
        $parent = ResourceBooking::findOrFail($parentBookingId);

        $intervalDays = match ($frequency) {
            'daily' => 1,
            'weekly' => 7,
            default => throw ValidationException::withMessages([
                'frequency' => "Unsupported recurrence frequency '{$frequency}' — only daily and weekly are supported.",
            ]),
        };

        $instances = new Collection([$parent]);

        for ($i = 1; $i < $occurrences; $i++) {
            $offsetDays = $intervalDays * $i;

            $instance = $this->requestBooking->execute(new RequestBookingData(
                schoolId: $parent->school_id,
                termId: $parent->term_id,
                resourceId: $parent->resource_id,
                bookingType: $parent->booking_type,
                purpose: $parent->purpose,
                startsAt: $parent->starts_at->copy()->addDays($offsetDays),
                endsAt: $parent->ends_at->copy()->addDays($offsetDays),
                requestedByUserId: $requestedByUserId,
                expectedAttendance: $parent->expected_attendance,
                requestedByStaffId: $parent->requested_by_staff_id,
                departmentId: $parent->department_id,
                hirerName: $parent->hirer_name,
                hirerContact: $parent->hirer_contact,
                hirerOrganisation: $parent->hirer_organisation,
                hireAmountMinor: $parent->hire_amount_minor,
                depositAmountMinor: $parent->deposit_amount_minor,
                parentBookingId: $parent->id,
            ));

            $instances->push($instance);
        }

        $parent->update(['recurrence_rule' => "{$frequency};COUNT={$occurrences}"]);

        return $instances;
    }
}
