<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\AssetInsurance;

final class InsuranceExpiring
{
    public function __construct(
        public readonly AssetInsurance $policy,
        public readonly int $daysRemaining,
    ) {}
}
