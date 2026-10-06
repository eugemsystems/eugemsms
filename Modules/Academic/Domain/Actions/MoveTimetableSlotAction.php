<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\Exceptions\TimetableSlotClashException;
use Modules\Academic\Domain\Support\TimetableClashDetector;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-MoveTimetableSlot (Book E ACA-03 §3/§7 ⭐). Moves a placed lesson to another cycle day and
 * period — what dragging a tile in the editor does. The same four unconditional clash levels
 * (teacher, venue, class, learner) as placing one are checked against whatever already occupies the
 * target cell, ignoring the lesson itself, and a clash refuses the move with the conflict named and
 * leaves the lesson where it was. A locked lesson, one half of a double lesson, a published
 * timetable, and a target that is not a teachable period of the timetable's structure are refused.
 */
final class MoveTimetableSlotAction extends Action
{
    public function __construct(private readonly TimetableClashDetector $detector) {}

    public function execute(int $slotId, int $cycleDay, int $periodNumber): TimetableSlot
    {
        $slot = TimetableSlot::query()->findOrFail($slotId);
        $timetable = Timetable::query()->findOrFail($slot->timetable_id);

        if ($timetable->status === 'published') {
            throw new InvalidStateTransitionException('A published timetable cannot be edited; make changes through a cover or a new version.');
        }

        if ($slot->is_locked) {
            throw new InvalidStateTransitionException('This lesson is locked in place.');
        }

        if ($slot->is_double || $slot->double_partner_slot_id !== null) {
            throw new InvalidStateTransitionException('A double lesson moves as a pair, which is not supported yet; remove and place it again.');
        }

        $target = PeriodSlot::query()->where('structure_id', $timetable->structure_id)->where('cycle_day', $cycleDay)->where('period_number', $periodNumber)->first();

        if ($target === null || ! $target->is_teachable) {
            throw new InvalidStateTransitionException('That is not a teaching period of this timetable.');
        }

        if ($slot->cycle_day === $cycleDay && $slot->period_number === $periodNumber) {
            return $slot;
        }

        $proposed = $slot->replicate();
        $proposed->cycle_day = $cycleDay;
        $proposed->period_number = $periodNumber;

        $concurrent = TimetableSlot::query()
            ->where('timetable_id', $timetable->id)->where('cycle_day', $cycleDay)->where('period_number', $periodNumber)
            ->whereKeyNot($slot->id)->get();

        $clashes = $this->detector->clashesForProposed($proposed, $concurrent);

        if ($clashes->isNotEmpty()) {
            throw TimetableSlotClashException::forClashes($clashes);
        }

        return $this->transaction(function () use ($slot, $cycleDay, $periodNumber, $target): TimetableSlot {
            $slot->update(['cycle_day' => $cycleDay, 'period_number' => $periodNumber, 'period_slot_id' => $target->id]);

            return $slot;
        });
    }
}
