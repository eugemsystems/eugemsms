<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Events;

use Modules\Farm\Models\ProductionUnit;

final class MortalityRateExceeded
{
    public function __construct(
        public readonly ProductionUnit $productionUnit,
        public readonly float $mortalityPercent,
    ) {}
}
