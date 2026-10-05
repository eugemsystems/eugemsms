<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreatePayGradeNotchData
{
    public function __construct(
        public int $schoolId,
        public int $gradeId,
        public string $notch,
        public int $basicSalaryMinor,
        public string $currency,
        public CarbonInterface $effectiveFrom,
    ) {}
}
