<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Listeners;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\Events\InspectionRecorded;
use Modules\Boarding\Models\RoomInspection;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\Term;
use Modules\Sport\Models\HousePoint;

/**
 * Book H2 OPS-07 §3/BR-OPS-07-008 — a real, additive listener on
 * `Modules\Boarding`'s already-shipped `InspectionRecorded` event
 * (Book F BRD-01), not an edit to `RecordRoomInspectionAction`
 * itself. `RoomInspection` links to a house only by traversing
 * `room -> hostel -> house_id`, and `Hostel.house_id` is nullable —
 * this silently no-ops when that chain doesn't resolve to a house,
 * which is not an error, just nothing to attribute the points to.
 * Points are the inspection's own percentage score (`total_score /
 * max_score`) scaled to a 10-point competition-equivalent, then
 * weighted — `max_score` of zero or null is treated the same as "no
 * house" (nothing to attribute).
 */
final class RecordHousePointsFromInspectionListener
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function handle(InspectionRecorded $event): void
    {
        $inspection = $event->inspection;

        if ((float) $inspection->max_score <= 0.0 || $inspection->total_score === null) {
            return;
        }

        $houseId = $inspection->room?->hostel?->house_id;

        if ($houseId === null) {
            return;
        }

        $academicYearId = Term::find($inspection->term_id)?->academic_year_id;

        if ($academicYearId === null) {
            return;
        }

        $weight = (float) $this->settings->get('sport.inspection_house_points_weight', new ScopeChain(schoolId: $inspection->school_id));
        $percentage = (float) $inspection->total_score / (float) $inspection->max_score;

        HousePoint::create([
            'school_id' => $inspection->school_id,
            'academic_year_id' => $academicYearId,
            'term_id' => $inspection->term_id,
            'house_id' => $houseId,
            'source_type' => 'inspection',
            'source_id' => $inspection->id,
            'points' => round($percentage * 10, 2) * $weight,
            'reason' => $this->reasonFor($inspection),
            'awarded_at' => Carbon::now(),
        ]);
    }

    private function reasonFor(RoomInspection $inspection): string
    {
        return "Room inspection #{$inspection->id} — {$inspection->grade}";
    }
}
