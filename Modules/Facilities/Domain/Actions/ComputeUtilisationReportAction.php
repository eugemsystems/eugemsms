<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\School;
use Modules\Facilities\Domain\DataObjects\UtilisationResult;
use Modules\Facilities\Models\BookableResource;
use Modules\Facilities\Models\ResourceBooking;

/**
 * ACT-ComputeUtilisationReport (Book H2 OPS-05 §3/BR-OPS-05-009).
 * Utilisation and hire revenue, per resource, per term.
 */
final class ComputeUtilisationReportAction extends Action
{
    public function execute(int $resourceId, int $termId): UtilisationResult
    {
        $resource = BookableResource::findOrFail($resourceId);

        $bookings = ResourceBooking::where('resource_id', $resource->id)
            ->where('term_id', $termId)
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->get(['starts_at', 'ends_at', 'hire_amount_minor']);

        $bookedHours = 0.0;
        $hireRevenueMinor = 0;

        foreach ($bookings as $booking) {
            $bookedHours += $booking->starts_at->diffInMinutes($booking->ends_at, true) / 60;
            $hireRevenueMinor += $booking->hire_amount_minor ?? 0;
        }

        return new UtilisationResult(
            resourceId: $resource->id,
            bookingCount: $bookings->count(),
            bookedHours: round($bookedHours, 2),
            hireRevenueMinor: $hireRevenueMinor,
            currency: School::findOrFail($resource->school_id)->base_currency,
        );
    }
}
