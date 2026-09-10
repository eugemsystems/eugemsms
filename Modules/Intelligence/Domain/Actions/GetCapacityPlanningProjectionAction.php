<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Modules\Academic\Models\Venue;
use Modules\Boarding\Models\Hostel;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\EnrolmentForecast;

/**
 * ACT-GetCapacityPlanningProjection (Book J INT-03 §2/BR-INT-03-012).
 * Reads `BRD-01`'s own hostel `capacity` and `ACA-03`'s own venue
 * `capacity` directly — never recalculated here — alongside this
 * module's own enrolment forecast, exactly as BR-INT-03-012 requires.
 */
final class GetCapacityPlanningProjectionAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{projected_total_intake: int, hostel_capacity: int, venue_capacity: int}
     */
    public function execute(int $schoolId, int $academicYearId): array
    {
        return [
            'projected_total_intake' => (int) EnrolmentForecast::where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->sum('projected_intake'),
            'hostel_capacity' => (int) Hostel::where('school_id', $schoolId)->where('is_active', true)->sum('capacity'),
            'venue_capacity' => (int) Venue::where('school_id', $schoolId)->where('is_active', true)->sum('capacity'),
        ];
    }
}
