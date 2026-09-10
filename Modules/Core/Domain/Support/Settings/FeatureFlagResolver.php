<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Settings;

use Modules\Core\Models\FeatureFlag;
use Modules\Core\Models\FeatureFlagOverride;

/**
 * Book A CORE-04 §4/BR-CORE-04-016. Resolution order: user override →
 * school override → tenant override → percentage rollout (hashed on
 * school id for stability — the same school always lands on the same
 * side of the rollout, rather than flapping per request) → global
 * default.
 */
final class FeatureFlagResolver
{
    public function isEnabled(string $key, ?ScopeChain $chain = null): bool
    {
        $flag = FeatureFlag::where('key', $key)->first();

        if ($flag === null) {
            return false;
        }

        $chain ??= ScopeChain::fromCurrentContext();

        if ($chain->userId !== null) {
            $override = $this->overrideFor($flag, 'user', $chain->userId);

            if ($override !== null) {
                return $override->is_enabled;
            }
        }

        if ($chain->schoolId !== null) {
            $override = $this->overrideFor($flag, 'school', $chain->schoolId);

            if ($override !== null) {
                return $override->is_enabled;
            }
        }

        if ($chain->tenantId !== null) {
            $override = $this->overrideFor($flag, 'tenant', $chain->tenantId);

            if ($override !== null) {
                return $override->is_enabled;
            }
        }

        if ($flag->rollout_percentage > 0 && $chain->schoolId !== null) {
            return $this->withinRollout($flag, $chain->schoolId);
        }

        return $flag->is_globally_enabled;
    }

    private function overrideFor(FeatureFlag $flag, string $scopeType, int $scopeId): ?FeatureFlagOverride
    {
        return FeatureFlagOverride::where('feature_flag_id', $flag->id)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->first();
    }

    private function withinRollout(FeatureFlag $flag, int $schoolId): bool
    {
        $hash = crc32("{$flag->key}:{$schoolId}") % 100;

        return $hash < $flag->rollout_percentage;
    }
}
