<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Events;

use Modules\Transport\Models\VehicleCompliance;

final class VehicleComplianceExpiring
{
    public function __construct(
        public readonly VehicleCompliance $compliance,
        public readonly int $daysRemaining,
    ) {}
}
