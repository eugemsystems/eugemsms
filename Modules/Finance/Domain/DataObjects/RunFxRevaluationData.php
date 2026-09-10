<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RunFxRevaluationData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public CarbonInterface $revaluationDate,
        public int $performedByUserId,
    ) {}
}
