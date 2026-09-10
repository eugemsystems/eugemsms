<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Events;

use Modules\Transport\Models\VehicleIncident;

final class VehicleIncidentReported
{
    public function __construct(
        public readonly VehicleIncident $incident,
    ) {}
}
