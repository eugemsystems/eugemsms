<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordFixtureInjuryData
{
    public function __construct(
        public int $fixtureId,
        public int $termId,
        public int $studentId,
        public string $incidentType,
        public CarbonInterface $occurredAt,
        public string $description,
        public string $severity,
        public int $reportedByUserId,
        public ?string $firstAidGiven = null,
        public ?int $firstAiderStaffId = null,
    ) {}
}
