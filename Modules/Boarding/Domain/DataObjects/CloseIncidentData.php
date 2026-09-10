<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CloseIncidentData
{
    public function __construct(
        public int $incidentId,
        public int $closedByUserId,
    ) {}
}
