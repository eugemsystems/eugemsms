<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\Term;
use Modules\Intelligence\Domain\Events\StaffWellbeingConcern;
use Modules\Intelligence\Models\StaffWellbeingIndicator;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\LeaveType;
use Modules\People\Models\StaffWorkload;

/**
 * ACT-RecalculateStaffWellbeingIndicator (Book J INT-03 §2/BR-INT-03-009/
 * 010). `workload_utilisation_percent` is copied straight from
 * `Modules\People\Models\StaffWorkload`'s own `utilisation_percent`
 * (PPL-04) — never recomputed here. `sick_leave_days_trend` is a
 * best-effort read of `Modules\People\Models\LeaveRequest` against
 * whichever `LeaveType` this school has named for sick leave (matched
 * by code or name — the codebase has no fixed "sick" leave-type code
 * convention yet); it is left `null`, honestly, when no such type
 * exists, rather than guessing.
 */
final class RecalculateStaffWellbeingIndicatorAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $schoolId, int $staffId, int $termId): StaffWellbeingIndicator
    {
        $term = Term::findOrFail($termId);
        $workload = StaffWorkload::where('school_id', $schoolId)->where('staff_id', $staffId)->where('term_id', $termId)->first();
        $ceiling = (float) $this->settings->get('risk.staff_workload_watch_threshold_percent', new ScopeChain(schoolId: $schoolId));

        $consecutive = $this->consecutiveTermsOverCeiling($schoolId, $staffId, $term, $ceiling);
        $sickTrend = $this->sickLeaveTrend($schoolId, $staffId, $term);

        $flagLevel = match (true) {
            $consecutive >= 3 => 'concern',
            $consecutive >= 1 => 'watch',
            default => 'none',
        };

        return $this->transaction(function () use ($schoolId, $staffId, $termId, $workload, $consecutive, $sickTrend, $flagLevel): StaffWellbeingIndicator {
            $indicator = StaffWellbeingIndicator::updateOrCreate(
                ['school_id' => $schoolId, 'staff_id' => $staffId, 'term_id' => $termId],
                [
                    'workload_utilisation_percent' => $workload?->utilisation_percent,
                    'consecutive_terms_over_ceiling' => $consecutive,
                    'sick_leave_days_trend' => $sickTrend,
                    'flag_level' => $flagLevel,
                ],
            );

            if ($flagLevel === 'concern') {
                event(new StaffWellbeingConcern($indicator));
            }

            return $indicator;
        });
    }

    private function consecutiveTermsOverCeiling(int $schoolId, int $staffId, Term $term, float $ceiling): int
    {
        $consecutive = 0;
        $cursor = $term;

        while ($cursor !== null) {
            $workload = StaffWorkload::where('school_id', $schoolId)->where('staff_id', $staffId)->where('term_id', $cursor->id)->first();

            if ($workload === null || $workload->utilisation_percent === null || (float) $workload->utilisation_percent < $ceiling) {
                break;
            }

            $consecutive++;
            $cursor = Term::where('school_id', $schoolId)->where('starts_on', '<', $cursor->starts_on)->orderByDesc('starts_on')->first();
        }

        return $consecutive;
    }

    private function sickLeaveTrend(int $schoolId, int $staffId, Term $term): ?string
    {
        $sickTypeIds = LeaveType::where('school_id', $schoolId)
            ->where(fn ($q) => $q->where('code', 'like', '%sick%')->orWhere('name', 'like', '%sick%'))
            ->pluck('id');

        if ($sickTypeIds->isEmpty()) {
            return null;
        }

        $previousTerm = Term::where('school_id', $schoolId)->where('starts_on', '<', $term->starts_on)->orderByDesc('starts_on')->first();

        if ($previousTerm === null) {
            return null;
        }

        $currentDays = (float) LeaveRequest::where('school_id', $schoolId)->where('staff_id', $staffId)
            ->whereIn('leave_type_id', $sickTypeIds)->whereBetween('starts_on', [$term->starts_on, $term->ends_on])->sum('working_days');

        $previousDays = (float) LeaveRequest::where('school_id', $schoolId)->where('staff_id', $staffId)
            ->whereIn('leave_type_id', $sickTypeIds)->whereBetween('starts_on', [$previousTerm->starts_on, $previousTerm->ends_on])->sum('working_days');

        return $currentDays > $previousDays ? 'rising' : 'stable';
    }
}
