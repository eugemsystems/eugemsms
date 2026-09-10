<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\ConsumptionAnomaly;

final class ConsumptionAnomalyDetected
{
    public function __construct(
        public readonly ConsumptionAnomaly $anomaly,
    ) {}
}
