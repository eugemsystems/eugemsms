<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\Events\RollCallMissed;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CheckRollCallMissed (Book F BRD-02 §5/BR-BRD-02-008/
 * AC-BRD-02-009). "An unconducted roll is a failure, not a silence."
 * Checked per point/hostel/date, directly testable against a fixed
 * clock; running it against every due point across the school is a
 * scheduled command's job (a deployment step, not built in this
 * pass — mirrors `AdvanceEscalationLadderAction`'s own note).
 */
final class CheckRollCallMissedAction extends Action
{
    public function execute(int $rollCallPointId, int $hostelId, CarbonInterface $rollDate): bool
    {
        $point = RollCallPoint::findOrFail($rollCallPointId);

        $dueAt = $rollDate->copy()->setTimeFromTimeString($point->scheduled_time)->addMinutes($point->grace_minutes);

        if (Carbon::now()->lessThan($dueAt)) {
            return false;
        }

        $started = RollCall::query()
            ->where('roll_call_point_id', $point->id)
            ->where('hostel_id', $hostelId)
            ->whereDate('roll_date', $rollDate->toDateString())
            ->whereNotNull('started_at')
            ->exists();

        if ($started) {
            return false;
        }

        event(new RollCallMissed($point, $hostelId, $rollDate));

        return true;
    }
}
