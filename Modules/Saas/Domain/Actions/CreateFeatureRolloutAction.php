<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\FeatureFlag;
use Modules\Core\Models\FeatureFlagOverride;
use Modules\Saas\Domain\DataObjects\CreateFeatureRolloutData;
use Modules\Saas\Models\FeatureRollout;

/**
 * ACT-CreateFeatureRollout (Book J SAA-02 §3/§4/BR-SAA-02-004
 * (AC-SAA-02-002)). Always starts at `pilot` — operates `CORE-04`'s
 * own `feature_flags`/`feature_flag_overrides` (`scope_type =
 * 'tenant'`) rather than a second flag mechanism.
 */
final class CreateFeatureRolloutAction extends Action
{
    public function execute(CreateFeatureRolloutData $data): FeatureRollout
    {
        $flag = FeatureFlag::query()->where('key', $data->featureFlagKey)->firstOrFail();

        return $this->transaction(function () use ($flag, $data): FeatureRollout {
            foreach ($data->pilotTenantIds as $tenantId) {
                FeatureFlagOverride::updateOrCreate(
                    ['feature_flag_id' => $flag->id, 'scope_type' => 'tenant', 'scope_id' => $tenantId],
                    ['is_enabled' => true],
                );
            }

            return FeatureRollout::create([
                'feature_flag_key' => $flag->key,
                'rollout_stage' => 'pilot',
                'pilot_tenant_ids' => $data->pilotTenantIds,
                'started_at' => Carbon::now(),
                'started_by' => $data->startedBy,
                'notes' => $data->notes,
            ]);
        });
    }
}
