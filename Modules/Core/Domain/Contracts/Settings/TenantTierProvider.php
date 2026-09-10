<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Settings;

use Modules\Core\Models\Tenant;

/**
 * BR-CORE-04-007: a setting marked `is_locked_on_tier` cannot be changed
 * by a customer at or above that tier. Subscription tiers belong to
 * SAA-01 (not built yet) — `NullTenantTierProvider` reports the lowest
 * tier for everyone, so nothing is ever locked until SAA-01 binds a real
 * implementation.
 */
interface TenantTierProvider
{
    /**
     * @return string one of 'starter'|'standard'|'enterprise', lowest first
     */
    public function tierFor(Tenant $tenant): string;

    /**
     * True when $tier is at or above $threshold in the tier ordering.
     */
    public function isAtLeast(string $tier, string $threshold): bool;
}
