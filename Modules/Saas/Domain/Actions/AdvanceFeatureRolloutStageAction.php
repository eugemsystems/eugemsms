<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\FeatureFlag;
use Modules\Core\Models\FeatureFlagOverride;
use Modules\Saas\Domain\DataObjects\AdvanceFeatureRolloutStageData;
use Modules\Saas\Domain\Exceptions\FeatureRolloutAlreadyAtFinalStageException;
use Modules\Saas\Models\FeatureRollout;

/**
 * ACT-AdvanceFeatureRolloutStage (Book J SAA-02 §3/§4/BR-SAA-02-004
 * ⭐ (AC-SAA-02-002)). Moves exactly one step along `pilot → cohort →
 * percentage → general` — never a skip, never an automatic
 * escalation; a human calls this Action explicitly every time.
 */
final class AdvanceFeatureRolloutStageAction extends Action
{
    public function execute(AdvanceFeatureRolloutStageData $data): FeatureRollout
    {
        $rollout = FeatureRollout::query()->findOrFail($data->rolloutId);
        $currentIndex = array_search($rollout->rollout_stage, FeatureRollout::STAGE_ORDER, true);

        if ($currentIndex === count(FeatureRollout::STAGE_ORDER) - 1) {
            throw new FeatureRolloutAlreadyAtFinalStageException(
                "Feature rollout [{$rollout->id}] is already at its final stage [general]."
            );
        }

        $nextStage = FeatureRollout::STAGE_ORDER[$currentIndex + 1];
        $flag = FeatureFlag::query()->where('key', $rollout->feature_flag_key)->firstOrFail();

        return $this->transaction(function () use ($rollout, $nextStage, $flag, $data): FeatureRollout {
            $updates = ['rollout_stage' => $nextStage];

            match ($nextStage) {
                'cohort' => $this->applyCohort($flag, $data->cohortTenantIds ?? []),
                'percentage' => $this->applyPercentage($flag, $data->percentage ?? 0, $updates),
                // Only 'general' remains here — 'pilot' can never be a
                // "next" stage and the two prior arms exhaust the rest.
                default => $flag->update(['is_globally_enabled' => true]),
            };

            $rollout->update($updates);

            return $rollout->fresh();
        });
    }

    /**
     * @param  array<int, int>  $tenantIds
     */
    private function applyCohort(FeatureFlag $flag, array $tenantIds): void
    {
        foreach ($tenantIds as $tenantId) {
            FeatureFlagOverride::updateOrCreate(
                ['feature_flag_id' => $flag->id, 'scope_type' => 'tenant', 'scope_id' => $tenantId],
                ['is_enabled' => true],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function applyPercentage(FeatureFlag $flag, int $percentage, array &$updates): void
    {
        $flag->update(['rollout_percentage' => $percentage]);
        $updates['percentage'] = $percentage;
    }
}
