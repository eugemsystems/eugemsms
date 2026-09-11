<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Academic\Domain\DataObjects\CreateLessonPlanData;
use Modules\Academic\Domain\Exceptions\LessonDateNotOnTimetableSlotException;
use Modules\Academic\Domain\Support\CycleDayResolver;
use Modules\Academic\Models\LessonPlan;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\Term;

/**
 * ACT-CreateLessonPlan (Book K ACA-11 §4/BR-ACA-11-003). When linked
 * to a timetable slot, the lesson date is validated against that
 * slot's own scheduled cycle day (via the same `CycleDayResolver`
 * `ACA-03`'s attendance-session generation uses) rather than freely
 * entered.
 */
final class CreateLessonPlanAction extends Action
{
    public function __construct(
        private readonly CycleDayResolver $cycleDayResolver,
    ) {}

    public function execute(CreateLessonPlanData $data): LessonPlan
    {
        if ($data->timetableSlotId !== null) {
            $this->assertDateMatchesSlot($data->timetableSlotId, $data->lessonDate);
        }

        return $this->transaction(fn (): LessonPlan => LessonPlan::create([
            'school_id' => $data->schoolId,
            'scheme_of_work_id' => $data->schemeOfWorkId,
            'timetable_slot_id' => $data->timetableSlotId,
            'teacher_staff_id' => $data->teacherStaffId,
            'lesson_date' => $data->lessonDate->toDateString(),
            'topic' => $data->topic,
            'objectives' => $data->objectives,
            'activities' => $data->activities,
            'resources_needed' => $data->resourcesNeeded,
            'differentiation_notes' => $data->differentiationNotes,
            'status' => 'draft',
        ]));
    }

    private function assertDateMatchesSlot(int $timetableSlotId, CarbonInterface $lessonDate): void
    {
        $slot = TimetableSlot::findOrFail($timetableSlotId);
        $timetable = Timetable::findOrFail($slot->timetable_id);
        $term = Term::findOrFail($timetable->term_id);
        $structure = PeriodStructure::findOrFail($timetable->structure_id);
        $holidays = CalendarHoliday::query()->where('academic_year_id', $term->academic_year_id)->get();

        $cycleDay = $this->cycleDayResolver->cycleDayFor($lessonDate, $term, $holidays, $structure->cycle_days);

        if ($cycleDay !== $slot->cycle_day) {
            throw LessonDateNotOnTimetableSlotException::forSlot($slot->id, $lessonDate->toDateString());
        }
    }
}
