<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class IssueSaleableItemToLearnerData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $storeId,
        public int $itemId,
        public int $studentId,
        public float $quantity,
        public int $issuedByUserId,
        public CarbonInterface $issuedAt,
    ) {}
}
