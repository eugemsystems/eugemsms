<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Settings;

use Modules\Core\Domain\Contracts\Settings\TenantTierProvider;
use Modules\Core\Models\Tenant;

final class NullTenantTierProvider implements TenantTierProvider
{
    public function tierFor(Tenant $tenant): string
    {
        return 'starter';
    }

    public function isAtLeast(string $tier, string $threshold): bool
    {
        return false;
    }
}
