<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RunBulkAllocationData
{
    /**
     * @param  array<int, int>  $studentIds
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public array $studentIds,
        public CarbonInterface $effectiveFrom,
        public int $allocatedByUserId,
    ) {}
}
