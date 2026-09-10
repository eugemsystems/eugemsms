<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateTimetableSlotData;
use Modules\Academic\Domain\Exceptions\TimetableSlotClashException;
use Modules\Academic\Domain\Support\TimetableClashDetector;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateTimetableSlot (Book E ACA-03 §3/§7 ⭐/BR-ACA-03-006/007).
 * The grid editor's own save path — checks the same four clash levels
 * generation does, against the proposed slot, before it's ever
 * written. A hard clash refuses the whole write and names exactly
 * what it would have broken (`TimetableSlotClashException`); this
 * action has no soft-violation warning path of its own — see the
 * spec's own note that all four clash levels are unconditionally
 * hard, distinct from `timetable_constraints`' configurable hard/soft
 * rules.
 */
final class CreateTimetableSlotAction extends Action
{
    public function __construct(private readonly TimetableClashDetector $detector) {}

    public function execute(CreateTimetableSlotData $data): TimetableSlot
    {
        $timetable = Timetable::findOrFail($data->timetableId);

        $proposed = new TimetableSlot([
            'school_id' => $timetable->school_id,
            'timetable_id' => $timetable->id,
            'term_id' => $data->termId,
            'cycle_day' => $data->cycleDay,
            'period_number' => $data->periodNumber,
            'subject_id' => $data->subjectId,
            'class_id' => $data->classId,
            'teaching_group_id' => $data->teachingGroupId,
            'staff_id' => $data->staffId,
            'co_staff_id' => $data->coStaffId,
            'venue_id' => $data->venueId,
        ]);

        $concurrent = TimetableSlot::query()
            ->where('timetable_id', $timetable->id)
            ->where('cycle_day', $data->cycleDay)
            ->where('period_number', $data->periodNumber)
            ->get();

        $clashes = $this->detector->clashesForProposed($proposed, $concurrent);

        if ($clashes->isNotEmpty()) {
            throw TimetableSlotClashException::forClashes($clashes);
        }

        return $this->transaction(fn (): TimetableSlot => TimetableSlot::create([
            'school_id' => $timetable->school_id,
            'timetable_id' => $timetable->id,
            'term_id' => $data->termId,
            'period_slot_id' => $data->periodSlotId,
            'cycle_day' => $data->cycleDay,
            'period_number' => $data->periodNumber,
            'subject_id' => $data->subjectId,
            'class_id' => $data->classId,
            'teaching_group_id' => $data->teachingGroupId,
            'staff_id' => $data->staffId,
            'co_staff_id' => $data->coStaffId,
            'venue_id' => $data->venueId,
            'is_double' => $data->isDouble,
            'double_partner_slot_id' => $data->doublePartnerSlotId,
            'is_locked' => $data->isLocked,
            'notes' => $data->notes,
        ]));
    }
}
