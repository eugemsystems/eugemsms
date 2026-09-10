<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Carbon\CarbonInterface;
use Modules\Core\Models\Term;
use Modules\Core\Models\TermWeek;

/**
 * Book D ACA-02 §4 step 5/BR-ACA-02-007. Snapshots the proration inputs
 * a subject add or drop bills against: teaching days remaining in the
 * term from a given date, against the term's total teaching days.
 *
 * When `ACT-GenerateTermWeeks` (Book A CORE-03) has already run for the
 * term, weekdays inside its teaching weeks are counted directly — this
 * still doesn't subtract a single-day holiday that falls inside an
 * otherwise-teaching week, since `TermWeek` only flags whole weeks
 * on/off (a known, documented gap, not a silent one). Where no weeks
 * have been generated yet, a straight-line calendar-day ratio against
 * `term.teaching_days` is used instead. Either way, the caller stores
 * the result — see `SubjectEnrolmentChange::$proration_factor` — so a
 * later correction to either input never rewrites an issued charge.
 */
final class TermProrationCalculator
{
    /**
     * @return array{remaining: int, total: int}
     */
    public function remainingAndTotal(Term $term, CarbonInterface $from): array
    {
        $total = $term->teaching_days ?? $this->calendarDaysSpan($term->starts_on, $term->ends_on);

        $weeks = TermWeek::withoutGlobalScopes()
            ->where('term_id', $term->id)
            ->orderBy('starts_on')
            ->get();

        if ($weeks->isEmpty()) {
            $remaining = $this->straightLineRemaining($term, $from, $total);

            return ['remaining' => $remaining, 'total' => $total];
        }

        $remaining = $weeks
            ->filter(fn (TermWeek $week): bool => $week->is_teaching_week && $week->ends_on->greaterThanOrEqualTo($from->startOfDay()))
            ->sum(fn (TermWeek $week): int => $this->weekdaysInRange(
                $week->starts_on->greaterThan($from) ? $week->starts_on : $from,
                $week->ends_on,
            ));

        return ['remaining' => (int) $remaining, 'total' => $total];
    }

    private function straightLineRemaining(Term $term, CarbonInterface $from, int $total): int
    {
        $calendarTotal = $this->calendarDaysSpan($term->starts_on, $term->ends_on);

        if ($calendarTotal <= 0) {
            return 0;
        }

        $calendarRemaining = $this->calendarDaysSpan($from, $term->ends_on);

        return (int) round($total * ($calendarRemaining / $calendarTotal));
    }

    private function calendarDaysSpan(CarbonInterface $from, CarbonInterface $to): int
    {
        if ($from->greaterThan($to)) {
            return 0;
        }

        return (int) $from->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
    }

    private function weekdaysInRange(CarbonInterface $from, CarbonInterface $to): int
    {
        if ($from->greaterThan($to)) {
            return 0;
        }

        $count = 0;
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            if (! $cursor->isWeekend()) {
                $count++;
            }

            $cursor = $cursor->addDay();
        }

        return $count;
    }
}
