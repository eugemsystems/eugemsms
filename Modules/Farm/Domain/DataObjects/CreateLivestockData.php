<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateLivestockData
{
    public function __construct(
        public int $schoolId,
        public int $productionUnitId,
        public string $species,
        public string $purpose,
        public bool $isHerdRecord = false,
        public int $headCount = 1,
        public ?string $tagNumber = null,
        public ?string $breed = null,
        public ?string $sex = null,
        public ?CarbonInterface $dateOfBirth = null,
        public ?CarbonInterface $acquiredOn = null,
        public ?string $acquisitionType = null,
        public ?int $acquisitionCostMinor = null,
        public ?string $currency = null,
        public ?int $capitalizeCategoryId = null,
        public ?int $capitalizeCostCentreId = null,
        public ?int $capitalizeContraAccountId = null,
        public ?int $academicYearId = null,
        public ?int $termId = null,
        public ?int $performedByUserId = null,
    ) {}
}
