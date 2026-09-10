<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\Term;
use Modules\Core\Models\TermWeek;

/**
 * ACT-GenerateTermWeeks (Book A CORE-03 §4). BR-CORE-03-004:
 * `teaching_days` is computed from term dates minus weekends, holidays,
 * and half-term, recomputed whenever dates or holidays change. Excludes
 * holidays and half-term from `is_teaching_week`, per §4's note.
 */
final class GenerateTermWeeksAction extends Action
{
    /**
     * @return Collection<int, TermWeek>
     */
    public function execute(Term $term): Collection
    {
        return $this->transaction(function () use ($term): Collection {
            $holidays = CalendarHoliday::withoutGlobalScopes()
                ->where('school_id', $term->school_id)
                ->where('academic_year_id', $term->academic_year_id)
                ->get();

            TermWeek::withoutGlobalScopes()->where('term_id', $term->id)->delete();

            $weeks = collect();
            $weekNumber = 1;
            // startOfDay() throughout: endOfWeek() below sets the time to
            // 23:59:59.999999, and without renormalising, that drifts
            // forward into every subsequent week's $cursor (via
            // ->addDay()) and corrupts the <= comparisons against
            // $term->ends_on (a plain midnight date) — the term's very
            // last day would compare as "later than" its own end date
            // and get silently dropped from the count.
            $termStart = $term->starts_on->copy()->startOfDay();
            $termEnd = $term->ends_on->copy()->startOfDay();
            $cursor = $termStart->copy()->startOfWeek()->startOfDay();
            $teachingDays = 0;

            while ($cursor->lessThanOrEqualTo($termEnd)) {
                $weekEnd = $cursor->copy()->endOfWeek()->startOfDay();
                $rangeStart = $cursor->greaterThan($termStart) ? $cursor : $termStart;
                $rangeEnd = $weekEnd->lessThan($termEnd) ? $weekEnd : $termEnd;

                $isTeachingWeek = true;
                $label = null;

                if ($this->isHalfTermWeek($term, $cursor, $weekEnd)) {
                    $isTeachingWeek = false;
                    $label = 'Half Term';
                } elseif ($this->isEntirelyHoliday($holidays, $rangeStart, $rangeEnd)) {
                    $isTeachingWeek = false;
                    $label = 'Holiday';
                }

                $weeks->push(TermWeek::create([
                    'school_id' => $term->school_id,
                    'term_id' => $term->id,
                    'week_number' => $weekNumber,
                    'starts_on' => $rangeStart,
                    'ends_on' => $rangeEnd,
                    'is_teaching_week' => $isTeachingWeek,
                    'label' => $label,
                ]));

                $teachingDays += $this->countTeachingDays($rangeStart, $rangeEnd, $term, $holidays);

                $weekNumber++;
                $cursor = $weekEnd->copy()->addDay();
            }

            $term->teaching_days = $teachingDays;
            $term->save();

            return $weeks;
        });
    }

    private function isHalfTermWeek(Term $term, CarbonInterface $weekStart, CarbonInterface $weekEnd): bool
    {
        if ($term->half_term_starts_on === null || $term->half_term_ends_on === null) {
            return false;
        }

        return $weekStart->lessThanOrEqualTo($term->half_term_ends_on)
            && $weekEnd->greaterThanOrEqualTo($term->half_term_starts_on)
            && $term->half_term_starts_on->lessThanOrEqualTo($weekStart)
            && $term->half_term_ends_on->greaterThanOrEqualTo($weekEnd);
    }

    /**
     * @param  Collection<int, CalendarHoliday>  $holidays
     */
    private function isEntirelyHoliday(Collection $holidays, CarbonInterface $rangeStart, CarbonInterface $rangeEnd): bool
    {
        foreach ($holidays as $holiday) {
            if ($holiday->starts_on->lessThanOrEqualTo($rangeStart) && $holiday->ends_on->greaterThanOrEqualTo($rangeEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, CalendarHoliday>  $holidays
     */
    private function countTeachingDays(CarbonInterface $rangeStart, CarbonInterface $rangeEnd, Term $term, Collection $holidays): int
    {
        $count = 0;
        $day = $rangeStart->copy();

        while ($day->lessThanOrEqualTo($rangeEnd)) {
            if (! $day->isWeekend() && ! $this->isHoliday($day, $holidays) && ! $this->isHalfTerm($day, $term)) {
                $count++;
            }

            $day = $day->addDay();
        }

        return $count;
    }

    /**
     * @param  Collection<int, CalendarHoliday>  $holidays
     */
    private function isHoliday(CarbonInterface $day, Collection $holidays): bool
    {
        foreach ($holidays as $holiday) {
            if ($day->betweenIncluded($holiday->starts_on, $holiday->ends_on)) {
                return true;
            }
        }

        return false;
    }

    private function isHalfTerm(CarbonInterface $day, Term $term): bool
    {
        if ($term->half_term_starts_on === null || $term->half_term_ends_on === null) {
            return false;
        }

        return $day->betweenIncluded($term->half_term_starts_on, $term->half_term_ends_on);
    }
}
