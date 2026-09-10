<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class FailCropCycleData
{
    public function __construct(
        public int $academicYearId,
        public int $termId,
        public string $failureReason,
        public int $cropFailureExpenseAccountId,
        public int $originalExpenseAccountId,
        public int $performedByUserId,
        public ?CarbonInterface $effectiveAt = null,
    ) {}
}
