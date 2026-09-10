<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\CreateExamSlotPlanData;
use Modules\Academic\Domain\Support\CycleDayResolver;
use Modules\Academic\Models\ExamSlotPlan;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;

/**
 * ACT-CreateExamSlotPlan (Book E ACA-03 §2/BR-ACA-03-020 🇿🇼). Reserves
 * a public-examination period against the school's currently
 * published timetable for the term and computes the disruption
 * report by walking the exam date range: every published slot whose
 * class sits in an affected grade level, on the affected cycle days,
 * is one lost teaching period for that level/subject. If no
 * timetable is published yet for the term, the plan is still created
 * with an empty disruption report rather than refused — the reservation
 * itself doesn't depend on generation having happened.
 */
final class CreateExamSlotPlanAction extends Action
{
    public function __construct(private readonly CycleDayResolver $cycleDayResolver) {}

    public function execute(CreateExamSlotPlanData $data): ExamSlotPlan
    {
        $term = Term::findOrFail($data->termId);
        $disruption = $this->computeDisruption($term, $data);

        return $this->transaction(fn (): ExamSlotPlan => ExamSlotPlan::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $term->academic_year_id,
            'term_id' => $term->id,
            'name' => $data->name,
            'exam_body' => $data->examBody,
            'starts_on' => $data->startsOn->toDateString(),
            'ends_on' => $data->endsOn->toDateString(),
            'affected_levels' => $data->affectedLevels,
            'venues_reserved' => $data->venuesReserved,
            'staff_reserved' => $data->staffReserved,
            'disruption_report' => $disruption,
            'status' => 'draft',
            'created_by' => $data->createdByUserId,
            'created_at' => Carbon::now(),
        ]));
    }

    /**
     * @return array<int, array{grade_level_id: int, subject_id: int, periods_lost: int}>
     */
    private function computeDisruption(Term $term, CreateExamSlotPlanData $data): array
    {
        $timetable = Timetable::query()->where('term_id', $term->id)->where('status', 'published')->first();

        if ($timetable === null) {
            return [];
        }

        $structure = $timetable->structure;
        $holidays = CalendarHoliday::query()->where('academic_year_id', $term->academic_year_id)->get();

        $slots = TimetableSlot::query()->where('timetable_id', $timetable->id)->get();
        $classGradeLevel = SchoolClass::query()
            ->whereIn('id', $slots->pluck('class_id')->filter()->unique())
            ->pluck('grade_level_id', 'id');

        $lostPeriods = [];
        $cursor = $data->startsOn->copy()->startOfDay();
        $end = $data->endsOn->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $cycleDay = $this->cycleDayResolver->cycleDayFor($cursor, $term, $holidays, $structure->cycle_days);
            $cursor = $cursor->addDay();

            if ($cycleDay === null) {
                continue;
            }

            foreach ($slots->where('cycle_day', $cycleDay) as $slot) {
                $gradeLevelId = $slot->class_id !== null ? ($classGradeLevel[$slot->class_id] ?? null) : null;

                if ($gradeLevelId === null || ! in_array($gradeLevelId, $data->affectedLevels, true)) {
                    continue;
                }

                $key = "{$gradeLevelId}:{$slot->subject_id}";
                $lostPeriods[$key] = ($lostPeriods[$key] ?? 0) + 1;
            }
        }

        $report = [];

        foreach ($lostPeriods as $key => $count) {
            [$gradeLevelId, $subjectId] = explode(':', $key);
            $report[] = ['grade_level_id' => (int) $gradeLevelId, 'subject_id' => (int) $subjectId, 'periods_lost' => $count];
        }

        return $report;
    }
}
