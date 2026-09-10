<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\CreateLessonSubstitutionData;
use Modules\Academic\Domain\DataObjects\CreateSubstitutionsForLeaveData;
use Modules\Academic\Domain\Support\CycleDayResolver;
use Modules\Academic\Models\LessonSubstitution;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\Term;

/**
 * ACT-CreateSubstitutionsForLeave (Book E ACA-03 §6/BR-ACA-03-016/AC-ACA-03-006).
 * For every date in the leave span that falls on a teachable cycle
 * day, and every published-timetable slot the absent staff member
 * teaches on that cycle day, creates a pending substitution — the
 * mechanism `CreateSubstitutionsForApprovedLeaveListener` calls the
 * moment `PPL-04`'s `LeaveApproved` fires.
 */
final class CreateSubstitutionsForLeaveAction extends Action
{
    public function __construct(
        private readonly CreateLessonSubstitutionAction $createSubstitution,
        private readonly CycleDayResolver $cycleDayResolver,
    ) {}

    /**
     * @return Collection<int, LessonSubstitution>
     */
    public function execute(CreateSubstitutionsForLeaveData $data)
    {
        $created = collect();

        $timetables = Timetable::query()
            ->where('status', 'published')
            ->whereHas('slots', fn ($q) => $q->where('staff_id', $data->staffId))
            ->with(['slots' => fn ($q) => $q->where('staff_id', $data->staffId)])
            ->get();

        foreach ($timetables as $timetable) {
            $term = Term::findOrFail($timetable->term_id);
            $holidays = CalendarHoliday::query()->where('academic_year_id', $term->academic_year_id)->get();

            $cursor = $data->fromDate->copy()->startOfDay();
            $end = $data->toDate->copy()->startOfDay();

            while ($cursor->lte($end)) {
                $date = $cursor->copy();
                $cursor = $cursor->addDay();

                $structure = $timetable->structure;
                $cycleDay = $this->cycleDayResolver->cycleDayFor($date, $term, $holidays, $structure->cycle_days);

                if ($cycleDay === null) {
                    continue;
                }

                foreach ($timetable->slots->where('cycle_day', $cycleDay) as $slot) {
                    /** @var TimetableSlot $slot */
                    $created->push($this->createSubstitution->execute(new CreateLessonSubstitutionData(
                        timetableSlotId: $slot->id,
                        substitutionDate: $date,
                        absentStaffId: $data->staffId,
                        reason: $data->reason,
                        leaveRequestId: $data->leaveRequestId,
                    )));
                }
            }
        }

        return $created;
    }
}
