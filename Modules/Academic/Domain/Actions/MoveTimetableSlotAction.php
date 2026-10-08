<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\TimetableSlotMovePreview;
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
 *
 * **Gap closed (2026-10-08): the live drag-over clash panel.** `execute()`'s
 * entire pre-write check is now `preview()` — one source of truth, called
 * both by `execute()` itself and by `Editor::previewMove()` (once per cell a
 * dragged lesson enters), so the editor can name a conflict *before* the drop
 * rather than only after it.
 */
final class MoveTimetableSlotAction extends Action
{
    public function __construct(private readonly TimetableClashDetector $detector) {}

    /**
     * What moving `$slotId` to (`$cycleDay`, `$periodNumber`) would do, without
     * writing anything.
     */
    public function preview(int $slotId, int $cycleDay, int $periodNumber): TimetableSlotMovePreview
    {
        $slot = TimetableSlot::query()->findOrFail($slotId);
        $timetable = Timetable::query()->findOrFail($slot->timetable_id);

        if ($timetable->status === 'published') {
            return new TimetableSlotMovePreview($slot, null, 'A published timetable cannot be edited; make changes through a cover or a new version.', collect());
        }

        if ($slot->is_locked) {
            return new TimetableSlotMovePreview($slot, null, 'This lesson is locked in place.', collect());
        }

        if ($slot->is_double || $slot->double_partner_slot_id !== null) {
            return new TimetableSlotMovePreview($slot, null, 'A double lesson moves as a pair, which is not supported yet; remove and place it again.', collect());
        }

        $target = PeriodSlot::query()->where('structure_id', $timetable->structure_id)->where('cycle_day', $cycleDay)->where('period_number', $periodNumber)->first();

        if ($target === null || ! $target->is_teachable) {
            return new TimetableSlotMovePreview($slot, null, 'That is not a teaching period of this timetable.', collect());
        }

        if ($slot->cycle_day === $cycleDay && $slot->period_number === $periodNumber) {
            return new TimetableSlotMovePreview($slot, $target, null, collect());
        }

        $proposed = $slot->replicate();
        $proposed->cycle_day = $cycleDay;
        $proposed->period_number = $periodNumber;

        $concurrent = TimetableSlot::query()
            ->where('timetable_id', $timetable->id)->where('cycle_day', $cycleDay)->where('period_number', $periodNumber)
            ->whereKeyNot($slot->id)->get();

        $clashes = $this->detector->clashesForProposed($proposed, $concurrent);

        return new TimetableSlotMovePreview($slot, $target, null, $clashes);
    }

    public function execute(int $slotId, int $cycleDay, int $periodNumber): TimetableSlot
    {
        $preview = $this->preview($slotId, $cycleDay, $periodNumber);

        if ($preview->blockedReason !== null) {
            throw new InvalidStateTransitionException($preview->blockedReason);
        }

        if ($preview->clashes->isNotEmpty()) {
            throw TimetableSlotClashException::forClashes($preview->clashes);
        }

        if ($preview->target === null) {
            return $preview->slot;
        }

        if ($preview->slot->cycle_day === $cycleDay && $preview->slot->period_number === $periodNumber) {
            return $preview->slot;
        }

        $target = $preview->target;

        return $this->transaction(function () use ($preview, $cycleDay, $periodNumber, $target): TimetableSlot {
            $preview->slot->update(['cycle_day' => $cycleDay, 'period_number' => $periodNumber, 'period_slot_id' => $target->id]);

            return $preview->slot;
        });
    }
}
