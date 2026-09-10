<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateFeeLiabilityData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public int $guardianId,
        public string $shareType,
        public int $createdByUserId,
        public ?int $componentId = null,
        public ?string $sharePercent = null,
        public ?int $shareAmountMinor = null,
        public ?string $currency = null,
        public int $priority = 100,
        public ?CarbonInterface $effectiveFrom = null,
    ) {}
}
