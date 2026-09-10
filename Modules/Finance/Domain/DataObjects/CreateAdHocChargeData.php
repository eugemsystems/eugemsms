<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CreateAdHocChargeData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $studentId,
        public int $componentId,
        public string $description,
        public int $unitRateMinor,
        public string $currency,
        public int $raisedByUserId,
        public string $quantity = '1',
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?int $approvedByUserId = null,
    ) {}
}
