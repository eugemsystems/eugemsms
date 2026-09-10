<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\Events\LearnerFlaggedHighRisk;
use Modules\Intelligence\Domain\Events\RiskScoreRecomputed;
use Modules\Intelligence\Domain\Events\WithdrawalRiskFlagged;
use Modules\Intelligence\Domain\Registry\RiskIndicatorRegistry;
use Modules\Intelligence\Models\LearnerRiskScore;
use Modules\Intelligence\Models\RiskScoreWeight;
use Modules\Intelligence\Models\WithdrawalRiskFlag;

/**
 * ACT-ComputeLearnerRiskScore (Book J INT-03 §3 ⭐/BR-INT-03-001/002/
 * 003/004/005 (AC-INT-03-001/003)). Every registered learner indicator
 * is resolved against this student and term; a `null` result (no data,
 * or nothing adverse) is excluded rather than padded to zero, so
 * `contributing_factors` always lists real, explainable signals only
 * (BR-INT-03-001). A school's own `RiskScoreWeight` override replaces
 * an indicator's default weight; `is_enabled = false` drops it
 * entirely (BR-INT-03-003).
 *
 * Reaching the `critical` band both persists this row — the "At-risk
 * review queue" screen's own backing data, banded and sortable, is
 * this table, not a second one — and opens a `WithdrawalRiskFlag`
 * (deduplicated against any already-open flag for this student) so a
 * human reviews it. Neither ever contacts a guardian or triggers any
 * other automated action (BR-INT-03-004/AC-INT-03-003).
 *
 * **Band cutoffs.** The spec states no numeric boundaries, only its
 * own worked example (68.5 → "high"). This pass uses 25/50/75 as
 * low/medium/high/critical cutoffs — low enough to keep the worked
 * example's own figure inside "high", not a value taken from the spec
 * itself.
 */
final class ComputeLearnerRiskScoreAction extends Action
{
    public function execute(int $schoolId, int $studentId, int $termId): LearnerRiskScore
    {
        $weights = RiskScoreWeight::where('school_id', $schoolId)->get()->keyBy('indicator_key');
        $factors = [];
        $composite = 0.0;

        foreach (RiskIndicatorRegistry::forAppliesTo('learner') as $indicator) {
            $override = $weights->get($indicator->key);

            if ($override !== null && ! $override->is_enabled) {
                continue;
            }

            $weight = $override?->weight ?? $indicator->defaultWeight;
            $result = ($indicator->resolver)($schoolId, $studentId, $termId);

            if ($result === null) {
                continue;
            }

            $contribution = round($weight * $result->severityPercent / 100, 2);
            $composite += $contribution;

            $factors[] = [
                'indicator' => $indicator->key,
                'plain_language' => $result->plainLanguage,
                'weight' => $weight,
                'contribution' => $contribution,
                'source' => $result->source,
            ];
        }

        $composite = min(100.0, round($composite, 2));
        $band = $this->band($composite);

        return $this->transaction(function () use ($schoolId, $studentId, $termId, $composite, $band, $factors): LearnerRiskScore {
            $score = LearnerRiskScore::updateOrCreate(
                ['school_id' => $schoolId, 'student_id' => $studentId, 'term_id' => $termId],
                ['composite_score' => $composite, 'risk_band' => $band, 'contributing_factors' => $factors, 'computed_at' => Carbon::now()],
            );

            if ($band === 'critical') {
                $hasOpenFlag = WithdrawalRiskFlag::where('school_id', $schoolId)->where('student_id', $studentId)->where('status', 'open')->exists();

                if (! $hasOpenFlag) {
                    $flag = WithdrawalRiskFlag::create([
                        'school_id' => $schoolId, 'student_id' => $studentId,
                        'flagged_at' => Carbon::now(), 'contributing_factors' => $factors, 'status' => 'open',
                    ]);

                    event(new WithdrawalRiskFlagged($flag));
                }
            }

            event(new RiskScoreRecomputed($score));

            if (in_array($band, ['high', 'critical'], true)) {
                event(new LearnerFlaggedHighRisk($score));
            }

            return $score;
        });
    }

    private function band(float $composite): string
    {
        return match (true) {
            $composite >= 75.0 => 'critical',
            $composite >= 50.0 => 'high',
            $composite >= 25.0 => 'medium',
            default => 'low',
        };
    }
}
