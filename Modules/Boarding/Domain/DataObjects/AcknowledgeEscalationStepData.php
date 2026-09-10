<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class AcknowledgeEscalationStepData
{
    public function __construct(
        public int $incidentId,
        public int $stepNumber,
        public int $actorId,
    ) {}
}
