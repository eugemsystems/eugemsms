<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ReportDisciplinaryCaseData
{
    public function __construct(
        public int $schoolId,
        public int $staffId,
        public string $category,
        public string $description,
        public CarbonInterface $incidentDate,
        public int $reportedByUserId,
        public bool $isConfidential = true,
    ) {}
}
