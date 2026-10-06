<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-RemoveTimetableSlot (Book E ACA-03 §3). Takes a lesson out of a draft timetable. A published
 * timetable, a locked lesson and one half of a double lesson are refused.
 */
final class RemoveTimetableSlotAction extends Action
{
    public function execute(int $slotId): void
    {
        $slot = TimetableSlot::query()->findOrFail($slotId);

        if (Timetable::query()->findOrFail($slot->timetable_id)->status === 'published') {
            throw new InvalidStateTransitionException('A published timetable cannot be edited.');
        }

        if ($slot->is_locked || $slot->is_double || $slot->double_partner_slot_id !== null) {
            throw new InvalidStateTransitionException('A locked or double lesson cannot be removed here.');
        }

        $this->transaction(fn () => $slot->delete());
    }
}
