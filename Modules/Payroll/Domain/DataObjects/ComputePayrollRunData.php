<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ComputePayrollRunData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public CarbonInterface $payDate,
        public CarbonInterface $periodStart,
        public CarbonInterface $periodEnd,
        public int $computedByUserId,
        public string $runType = 'regular',
    ) {}
}
