<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ReportVehicleIncidentData
{
    /**
     * @param  array<int, int>|null  $learnersInvolved
     * @param  array<int, int>|null  $photoFileIds
     */
    public function __construct(
        public int $schoolId,
        public int $vehicleId,
        public string $incidentType,
        public CarbonInterface $occurredAt,
        public string $location,
        public string $description,
        public int $reportedByUserId,
        public ?int $driverId = null,
        public ?int $tripId = null,
        public ?array $learnersInvolved = null,
        public bool $injuries = false,
        public ?string $policeReportNumber = null,
        public ?int $estimatedDamageMinor = null,
        public ?array $photoFileIds = null,
        public ?int $termId = null,
    ) {}
}
