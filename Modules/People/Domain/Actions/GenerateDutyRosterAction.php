<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\GenerateDutyRosterData;
use Modules\People\Domain\Events\DutyAssigned;
use Modules\People\Domain\Exceptions\NoEligibleDutyStaffException;
use Modules\People\Models\DutyAssignment;
use Modules\People\Models\DutyRoster;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\Staff;

/**
 * ACT-GenerateDutyRoster (Book C PPL-04 §4/BR-PPL-04-015/017
 * (AC-PPL-04-010)). A greedy least-loaded assignment: for each slot,
 * in order, the eligible staff member (excluding anyone on approved
 * leave overlapping that slot) with the fewest duties so far this
 * roster gets it, ties broken by staff id for determinism. That
 * greedy rule is exactly what keeps every staff member's duty count
 * within one of the mean — the property AC-PPL-04-010 asks for,
 * without needing a separate balancing pass afterwards.
 *
 * Counts existing `assigned`/`completed` assignments on this roster
 * as the starting load, so calling this again to fill more slots
 * later still balances against history rather than resetting to
 * zero.
 */
final class GenerateDutyRosterAction extends Action
{
    /**
     * @return array{assignments: array<int, DutyAssignment>, skippedSlots: array<int, int>}
     */
    public function execute(GenerateDutyRosterData $data): array
    {
        $roster = DutyRoster::findOrFail($data->rosterId);

        $eligibleStaffIds = Staff::where('school_id', $roster->school_id)
            ->whereIn('status', ['probation', 'active'])
            ->when($roster->eligible_categories !== null, fn ($q) => $q->whereIn('staff_category', $roster->eligible_categories))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($eligibleStaffIds === []) {
            throw NoEligibleDutyStaffException::forRoster($roster->id);
        }

        $dutyCounts = array_fill_keys($eligibleStaffIds, 0);

        $existingCounts = DutyAssignment::where('roster_id', $roster->id)
            ->whereIn('status', ['assigned', 'completed'])
            ->whereIn('staff_id', $eligibleStaffIds)
            ->selectRaw('staff_id, count(*) as total')
            ->groupBy('staff_id')
            ->pluck('total', 'staff_id');

        foreach ($existingCounts as $staffId => $count) {
            $dutyCounts[$staffId] = (int) $count;
        }

        return $this->transaction(function () use ($roster, $data, $eligibleStaffIds, $dutyCounts): array {
            $assignments = [];
            $skippedSlots = [];

            foreach ($data->slots as $index => $slot) {
                $onLeaveStaffIds = LeaveRequest::whereIn('staff_id', $eligibleStaffIds)
                    ->where('status', 'approved')
                    ->where('starts_on', '<=', $slot->endsAt->toDateString())
                    ->where('ends_on', '>=', $slot->startsAt->toDateString())
                    ->pluck('staff_id')
                    ->all();

                $availableStaffIds = array_values(array_diff($eligibleStaffIds, $onLeaveStaffIds));

                if ($availableStaffIds === []) {
                    $skippedSlots[] = $index;

                    continue;
                }

                usort($availableStaffIds, fn (int $a, int $b): int => $dutyCounts[$a] <=> $dutyCounts[$b] ?: $a <=> $b);
                $chosenStaffId = $availableStaffIds[0];

                $assignment = DutyAssignment::create([
                    'school_id' => $roster->school_id,
                    'roster_id' => $roster->id,
                    'staff_id' => $chosenStaffId,
                    'starts_at' => $slot->startsAt,
                    'ends_at' => $slot->endsAt,
                    'status' => 'assigned',
                ]);

                $dutyCounts[$chosenStaffId]++;
                $assignments[] = $assignment;

                event(new DutyAssigned($assignment));
            }

            return ['assignments' => $assignments, 'skippedSlots' => $skippedSlots];
        });
    }
}
