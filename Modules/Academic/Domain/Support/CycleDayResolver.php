<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\Term;

/**
 * Book E ACA-03 §5/§6/BR-ACA-03-002. The cycle day for a calendar date
 * is derived from the term start, skipping weekends and
 * `CalendarHoliday` spans — shared by attendance session generation
 * and substitution creation so the two never disagree about what day
 * of the cycle a given date is.
 */
final class CycleDayResolver
{
    /**
     * @param  Collection<int, CalendarHoliday>  $holidays
     */
    public function isNonTeachingDay(CarbonInterface $date, Collection $holidays): bool
    {
        if ($date->isWeekend()) {
            return true;
        }

        return $holidays->contains(fn (CalendarHoliday $h): bool => $date->betweenIncluded($h->starts_on, $h->ends_on));
    }

    /**
     * @param  Collection<int, CalendarHoliday>  $holidays
     */
    public function cycleDayFor(CarbonInterface $date, Term $term, Collection $holidays, int $cycleDays): ?int
    {
        if ($date->lt($term->starts_on) || $date->gt($term->ends_on) || $this->isNonTeachingDay($date, $holidays)) {
            return null;
        }

        $teachingDaysSinceStart = 0;
        $cursor = $term->starts_on->copy()->startOfDay();
        $target = $date->copy()->startOfDay();

        while ($cursor->lt($target)) {
            if (! $this->isNonTeachingDay($cursor, $holidays)) {
                $teachingDaysSinceStart++;
            }

            $cursor = $cursor->addDay();
        }

        return ($teachingDaysSinceStart % $cycleDays) + 1;
    }
}
