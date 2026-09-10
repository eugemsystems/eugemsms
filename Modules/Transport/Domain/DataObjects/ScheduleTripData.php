<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ScheduleTripData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public CarbonInterface $tripDate,
        public string $tripType,
        public int $vehicleId,
        public int $driverId,
        public ?int $routeId = null,
        public ?int $escortStaffId = null,
        public ?string $purpose = null,
        public ?string $destination = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
    ) {}
}
