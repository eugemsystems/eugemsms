<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Events;

use Modules\Transport\Models\FuelLog;

final class FuelAnomalyDetected
{
    public function __construct(
        public readonly FuelLog $fuelLog,
        public readonly string $reason,
    ) {}
}
