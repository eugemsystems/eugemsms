<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\Registry\ChurnRiskIndicatorRegistry;
use Modules\Saas\Models\ChurnRiskFlag;

/**
 * ACT-RaiseChurnRiskFlag (Book J SAA-03 §4/BR-SAA-03-008
 * (AC-SAA-03-005)). Every registered indicator is resolved against
 * this tenant; a `null` result (nothing adverse to report) is
 * excluded rather than padded to zero, so `contributing_factors`
 * always lists real, explainable signals only — the same discipline
 * `Modules\Intelligence\Domain\Actions\ComputeLearnerRiskScoreAction`
 * (`INT-03`) already applies at the learner level. A composite below
 * the threshold raises nothing; an already-open flag is never
 * duplicated.
 */
final class RaiseChurnRiskFlagAction extends Action
{
    private const float THRESHOLD = 40.0;

    public function execute(int $tenantId): ?ChurnRiskFlag
    {
        $factors = [];
        $composite = 0.0;

        foreach (ChurnRiskIndicatorRegistry::all() as $indicator) {
            $result = ($indicator->resolver)($tenantId);

            if ($result === null) {
                continue;
            }

            $contribution = round($indicator->defaultWeight * $result->severityPercent / 100, 2);
            $composite += $contribution;

            $factors[] = [
                'indicator' => $indicator->key,
                'plain_language' => $result->plainLanguage,
                'weight' => $indicator->defaultWeight,
                'contribution' => $contribution,
                'source' => $result->source,
            ];
        }

        $composite = min(100.0, round($composite, 2));

        if ($composite < self::THRESHOLD || $factors === []) {
            return null;
        }

        $hasOpenFlag = ChurnRiskFlag::where('tenant_id', $tenantId)->where('status', 'open')->exists();

        if ($hasOpenFlag) {
            return null;
        }

        return $this->transaction(fn (): ChurnRiskFlag => ChurnRiskFlag::create([
            'tenant_id' => $tenantId,
            'flagged_at' => Carbon::now(),
            'contributing_factors' => $factors,
            'status' => 'open',
        ]));
    }
}
