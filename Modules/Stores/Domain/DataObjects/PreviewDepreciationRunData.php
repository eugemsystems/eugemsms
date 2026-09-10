<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

final readonly class PreviewDepreciationRunData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public string $periodMonth,
        public int $computedByUserId,
    ) {}
}
