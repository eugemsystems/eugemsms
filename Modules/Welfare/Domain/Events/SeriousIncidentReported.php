<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\HealthIncident;

final class SeriousIncidentReported
{
    public function __construct(
        public readonly HealthIncident $incident,
    ) {}
}
