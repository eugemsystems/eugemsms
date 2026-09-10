<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateIntakeData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $name,
        public int $gradeLevelId,
        public CarbonInterface $opensOn,
        public CarbonInterface $closesOn,
        public int $targetPlaces,
        public int $createdByUserId,
        public ?int $applicationFeeMinor = null,
        public ?string $applicationFeeCurrency = null,
        public ?int $acceptanceDepositMinor = null,
        public ?string $acceptanceDepositCurrency = null,
        public int $depositDeadlineDays = 14,
    ) {}
}
