<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

use Carbon\CarbonInterface;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallRecord;

/**
 * Book F BRD-02 §5 ⭐. When no completed roll call exists for the
 * date/hostel (nothing conducted yet today), `present` falls back to
 * `allocated` and `rollCallAvailable` is false — callers such as
 * `BRD-04` must show the nominal figure rather than inventing a
 * present count from nothing (mirroring that module's own "never
 * display an uncosted meal as costing nothing" discipline).
 */
final class EloquentLiveOccupancyProvider implements LiveOccupancyProvider
{
    public function liveOccupancy(CarbonInterface $date, string $meal, ?int $hostelId = null): LiveOccupancy
    {
        $allocationQuery = BedAllocation::query()
            ->where('status', 'confirmed')
            ->whereNull('effective_to');

        if ($hostelId !== null) {
            $allocationQuery->where('hostel_id', $hostelId);
        }

        $allocated = $allocationQuery->count();

        $rollCallQuery = RollCall::query()
            ->whereDate('roll_date', $date->toDateString())
            ->where('status', 'completed');

        if ($hostelId !== null) {
            $rollCallQuery->where('hostel_id', $hostelId);
        }

        $rollCallIds = $rollCallQuery->orderByDesc('completed_at')->pluck('id');

        if ($rollCallIds->isEmpty()) {
            return new LiveOccupancy($allocated, $allocated, 0, 0, 0, false);
        }

        $records = RollCallRecord::query()->whereIn('roll_call_id', $rollCallIds)->get();

        $present = $records->where('status', 'present')->count();
        $onExeat = $records->where('status', 'exeat')->count();
        $inSickBay = $records->whereIn('status', ['sick_bay', 'hospital'])->count();
        $missing = $records->where('status', 'missing')->count();

        return new LiveOccupancy($allocated, $present, $onExeat, $inSickBay, $missing, true);
    }
}
