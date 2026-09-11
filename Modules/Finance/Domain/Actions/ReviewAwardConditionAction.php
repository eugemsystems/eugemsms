<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\Events\AwardConditionFailed;
use Modules\Finance\Domain\Events\AwardSuspended;
use Modules\Finance\Models\DiscountAward;

/**
 * ACT-ReviewAwardCondition (Book K FIN-07 §4/BR-FIN-07-007/011
 * (AC-FIN-07-005)). Validates against `ACA-05`'s actual, published
 * `TermResult.average_percent` — never a self-reported figure. A
 * failed condition suspends the award pending human review; it is
 * never auto-revoked (a genuinely separate, explicit act —
 * `RevokeAwardAction`).
 */
final class ReviewAwardConditionAction extends Action
{
    public function execute(int $awardId, int $termId): DiscountAward
    {
        $award = DiscountAward::query()->with('scheme')->findOrFail($awardId);
        $scheme = $award->scheme;

        $conditionMet = true;

        if ($scheme->requires_academic_threshold && $scheme->minimum_average_percent !== null) {
            $result = TermResult::where('student_id', $award->student_id)
                ->where('term_id', $termId)
                ->first();

            $conditionMet = $result?->average_percent !== null
                && (float) $result->average_percent >= (float) $scheme->minimum_average_percent;
        }

        return $this->transaction(function () use ($award, $conditionMet): DiscountAward {
            $award->update([
                'condition_met' => $conditionMet,
                'condition_last_checked_at' => Carbon::now(),
                'status' => $conditionMet ? $award->status : 'suspended',
            ]);

            $fresh = $award->fresh();

            if (! $conditionMet) {
                event(new AwardConditionFailed($fresh));
                event(new AwardSuspended($fresh));
            }

            return $fresh;
        });
    }
}
