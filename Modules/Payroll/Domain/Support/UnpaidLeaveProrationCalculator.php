<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Support;

use Carbon\CarbonInterface;
use Modules\People\Models\LeaveRequest;

/**
 * Book H3 PPL-05 §3/BR-PPL-05-011. Pro-rates gross (and every
 * percentage-based component) for unpaid leave taken from `PPL-04`
 * during the pay period. Simplified against the spec's own
 * `TermProrationCalculator` (Book D ACA-02) precedent: this counts
 * calendar days in the pay period rather than term teaching days,
 * since a payroll period isn't scoped to one term's calendar.
 */
final class UnpaidLeaveProrationCalculator
{
    /**
     * @return array{factor: string, unpaidDays: string}
     */
    public function calculate(int $staffId, CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        // `diffInDays()` returns a float that can land a hair below the
        // true integer (e.g. 29.999999999988, from sub-second precision
        // between a start-of-day and an end-of-day instant) — rounding
        // here, before any bcmath, keeps that noise from truncating into
        // a spurious fraction of a day inside `bcsub()` below.
        $periodDays = (int) round($periodStart->diffInDays($periodEnd)) + 1;

        $unpaidDays = (string) LeaveRequest::query()
            ->where('staff_id', $staffId)
            ->where('status', 'approved')
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', false))
            ->where('starts_on', '<=', $periodEnd->toDateString())
            ->where('ends_on', '>=', $periodStart->toDateString())
            ->sum('working_days');

        if (bccomp($unpaidDays, (string) $periodDays, 1) > 0) {
            $unpaidDays = (string) $periodDays;
        }

        $factor = $periodDays > 0 ? bcdiv(bcsub((string) $periodDays, $unpaidDays, 1), (string) $periodDays, 10) : '1';

        return ['factor' => $factor, 'unpaidDays' => $unpaidDays];
    }
}
