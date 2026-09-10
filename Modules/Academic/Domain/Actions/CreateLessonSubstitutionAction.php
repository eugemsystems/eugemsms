<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateLessonSubstitutionData;
use Modules\Academic\Models\LessonSubstitution;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateLessonSubstitution (Book E ACA-03 §2/§6). The per-slot
 * primitive — idempotent on `(timetable_slot_id, substitution_date)`
 * via the table's own unique constraint, so calling it twice for the
 * same lesson/date is a harmless no-op, never a duplicate pending
 * cover.
 */
final class CreateLessonSubstitutionAction extends Action
{
    public function execute(CreateLessonSubstitutionData $data): LessonSubstitution
    {
        $slot = TimetableSlot::findOrFail($data->timetableSlotId);

        $existing = LessonSubstitution::query()
            ->where('timetable_slot_id', $slot->id)
            ->whereDate('substitution_date', $data->substitutionDate->toDateString())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->transaction(fn (): LessonSubstitution => LessonSubstitution::create([
            'school_id' => $slot->school_id,
            'term_id' => $slot->term_id,
            'timetable_slot_id' => $slot->id,
            'substitution_date' => $data->substitutionDate->toDateString(),
            'absent_staff_id' => $data->absentStaffId,
            'reason' => $data->reason,
            'leave_request_id' => $data->leaveRequestId,
            'status' => 'pending',
        ]));
    }
}
