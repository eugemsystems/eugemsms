<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\DataObjects;

final readonly class CreateBookableResourceData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $resourceType,
        public int $costCentreId,
        public ?int $venueId = null,
        public ?int $vehicleId = null,
        public ?int $capacity = null,
        public bool $isExternallyHireable = false,
        public ?int $hireRateMinor = null,
        public ?string $hireRateUnit = null,
        public ?string $hireCurrency = null,
        public ?int $depositMinor = null,
        public int $requiresSetupMinutes = 0,
        public int $requiresCleaningMinutes = 0,
        public int $bookingLeadTimeHours = 24,
        public ?int $incomeAccountId = null,
    ) {}
}
