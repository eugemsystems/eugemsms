<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\GenerateAttendanceSessionData;
use Modules\Academic\Domain\DataObjects\GenerateAttendanceSessionsFromTimetableData;
use Modules\Academic\Domain\Events\AttendanceSessionsGenerated;
use Modules\Academic\Domain\Support\CycleDayResolver;
use Modules\Academic\Models\AttendanceSession;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableException;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\Term;

/**
 * ACT-GenerateAttendanceSessionsFromTimetable (Book E ACA-03 §5 ⭐).
 * The `AttendanceSessionGenerator` interface `BR-ACA-04-002` was
 * written against — closes it for the timetable-driven half; a bare
 * daily-schedule fallback with no published timetable at all is
 * `ACA-04`'s own concern per that rule's literal wording ("or from a
 * daily schedule where no timetable exists"), not built here.
 *
 * Idempotent by construction (BR-ACA-03-005 §5 step 6): every session
 * is checked for existence before creation, never blindly re-created,
 * so re-running (nightly, or after republishing) never duplicates a
 * session and — because this only ever creates, never updates — a
 * past session that already carries marks is never touched
 * (AC-ACA-03-005).
 *
 * `timetable_exceptions` scoped `whole_school` or `class` are
 * honoured; `section`/`level`-scoped exceptions are not evaluated in
 * this pass (would need the class→grade-level→section chain resolved
 * per slot) — a documented gap, not a silent one.
 */
final class GenerateAttendanceSessionsFromTimetableAction extends Action
{
    public function __construct(
        private readonly GenerateAttendanceSessionAction $generateSession,
        private readonly CycleDayResolver $cycleDayResolver,
    ) {}

    /**
     * @return array{created: int, skipped: int}
     */
    public function execute(GenerateAttendanceSessionsFromTimetableData $data): array
    {
        $timetable = Timetable::findOrFail($data->timetableId);
        $term = Term::findOrFail($timetable->term_id);
        $structure = PeriodStructure::findOrFail($timetable->structure_id);

        $holidays = CalendarHoliday::query()->where('academic_year_id', $term->academic_year_id)->get();
        $slots = TimetableSlot::query()->where('timetable_id', $timetable->id)->with('periodSlot')->get();
        $exceptions = TimetableException::query()->where('term_id', $term->id)
            ->whereBetween('exception_date', [$data->fromDate->toDateString(), $data->toDate->toDateString()])
            ->get()
            ->groupBy(fn (TimetableException $e): string => $e->exception_date->toDateString());

        $created = 0;
        $skipped = 0;
        $cursor = $data->fromDate->copy()->startOfDay();
        $end = $data->toDate->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $date = $cursor->copy();
            $cursor = $cursor->addDay();

            if ($date->lt($term->starts_on) || $date->gt($term->ends_on) || $this->cycleDayResolver->isNonTeachingDay($date, $holidays)) {
                continue;
            }

            $dayExceptions = $exceptions->get($date->toDateString(), collect());
            $wholeSchool = $dayExceptions->first(fn (TimetableException $e): bool => $e->affected_scope === 'whole_school');

            if ($wholeSchool !== null && ($wholeSchool->exception_type === 'no_lessons' || $wholeSchool->suppresses_attendance)) {
                continue;
            }

            $suppressedClassIds = $dayExceptions
                ->filter(fn (TimetableException $e): bool => $e->affected_scope === 'class' && $e->suppresses_attendance)
                ->pluck('scope_id');

            $cycleDay = $this->cycleDayResolver->cycleDayFor($date, $term, $holidays, $structure->cycle_days);

            if ($cycleDay === null) {
                continue;
            }

            foreach ($slots->where('cycle_day', $cycleDay) as $slot) {
                if (! $slot->periodSlot->requires_attendance) {
                    continue;
                }

                if ($slot->class_id !== null && $suppressedClassIds->contains($slot->class_id)) {
                    continue;
                }

                $exists = AttendanceSession::query()
                    ->whereDate('session_date', $date->toDateString())
                    ->where('mode', 'period')
                    ->where('class_id', $slot->class_id)
                    ->where('teaching_group_id', $slot->teaching_group_id)
                    ->where('period_number', $slot->period_number)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $this->generateSession->execute(new GenerateAttendanceSessionData(
                    schoolId: $timetable->school_id,
                    academicYearId: $timetable->academic_year_id,
                    termId: $term->id,
                    sessionDate: $date,
                    mode: 'period',
                    classId: $slot->class_id,
                    teachingGroupId: $slot->teaching_group_id,
                    subjectId: $slot->subject_id,
                    periodNumber: $slot->period_number,
                    staffId: $slot->staff_id,
                    timetableSlotId: $slot->id,
                ));
                $created++;
            }
        }

        event(new AttendanceSessionsGenerated($timetable->id, $created, $skipped));

        return ['created' => $created, 'skipped' => $skipped];
    }
}
