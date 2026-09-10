<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordCropInputData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $cropCycleId,
        public int $storeId,
        public int $itemId,
        public string $inputType,
        public string $description,
        public float $quantity,
        public string $unit,
        public CarbonInterface $appliedOn,
        public int $appliedByUserId,
    ) {}
}
