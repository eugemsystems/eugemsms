<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ComputePayslipData
{
    public function __construct(
        public int $payrollRunId,
        public int $staffId,
        public CarbonInterface $payDate,
        public CarbonInterface $periodStart,
        public CarbonInterface $periodEnd,
    ) {}
}
