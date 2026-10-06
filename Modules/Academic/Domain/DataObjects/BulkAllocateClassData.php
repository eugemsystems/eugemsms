<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class BulkAllocateClassData
{
    /**
     * @param  array<int, int>  $studentIds
     */
    public function __construct(
        public array $studentIds,
        public int $classId,
        public int $termId,
        public int $allocatedByUserId,
        public CarbonInterface $effectiveFrom,
        public string $allocationType = 'initial',
    ) {}
}
