<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordHealthIncidentData
{
    /**
     * @param  array<int, string>|null  $witnesses
     * @param  array<int, int>|null  $photoFileIds
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $studentId,
        public string $incidentType,
        public CarbonInterface $occurredAt,
        public string $location,
        public string $description,
        public string $severity,
        public int $reportedByUserId,
        public ?string $activityAtTime = null,
        public ?array $witnesses = null,
        public ?string $firstAidGiven = null,
        public ?int $firstAiderStaffId = null,
        public ?array $photoFileIds = null,
    ) {}
}
