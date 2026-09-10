<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

use Carbon\CarbonInterface;

/**
 * Book F BRD-02 §5/Appendix A ⭐. The only sanctioned route to "how
 * many boarders are actually here right now" — `BRD-04` catering and
 * `OPS-06`'s emergency muster roll both consume this instead of
 * reading `bed_allocations`/`roll_call_records` directly. `$meal` is
 * accepted for interface fidelity with the spec's own signature but
 * unused today — no meal-granular roll call exists yet, so occupancy
 * is read from the most recent completed roll call on `$date`
 * regardless of which meal is asking.
 */
interface LiveOccupancyProvider
{
    public function liveOccupancy(CarbonInterface $date, string $meal, ?int $hostelId = null): LiveOccupancy;
}
