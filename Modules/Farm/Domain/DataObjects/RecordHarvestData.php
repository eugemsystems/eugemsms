<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordHarvestData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $cropCycleId,
        public CarbonInterface $harvestedOn,
        public float $quantityKg,
        public int $itemId,
        public int $farmProductionContraAccountId,
        public int $recordedByUserId,
        public string $destination = 'store',
        public ?string $qualityGrade = null,
        public ?float $moisturePercent = null,
    ) {}
}
