<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Events;

use Modules\Transport\Models\Vehicle;

final class VehicleGrounded
{
    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly string $reason,
    ) {}
}
