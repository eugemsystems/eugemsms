<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ReportMalpracticeIncidentData
{
    /**
     * @param  array<int, int>|null  $evidenceFileIds
     */
    public function __construct(
        public int $schoolId,
        public int $sessionId,
        public string $incidentType,
        public string $description,
        public int $reportedByUserId,
        public CarbonInterface $occurredAt,
        public ?int $paperId = null,
        public ?int $candidateId = null,
        public ?array $evidenceFileIds = null,
    ) {}
}
