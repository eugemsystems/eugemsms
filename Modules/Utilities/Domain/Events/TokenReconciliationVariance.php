<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Events;

use Modules\Utilities\Models\Meter;

final class TokenReconciliationVariance
{
    public function __construct(
        public readonly Meter $meter,
        public readonly float $creditedUnits,
        public readonly float $meteredConsumption,
        public readonly float $variancePercent,
    ) {}
}
