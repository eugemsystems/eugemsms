<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class DecideMalpracticeOutcomeData
{
    public function __construct(
        public int $incidentId,
        public string $outcome,
        public int $outcomeByUserId,
        public string $investigationNotes,
    ) {}
}
