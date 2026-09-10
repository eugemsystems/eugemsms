<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateStaffLoanData
{
    public function __construct(
        public int $schoolId,
        public int $staffId,
        public string $loanType,
        public int $principalMinor,
        public string $currency,
        public int $instalmentMinor,
        public int $instalmentCount,
        public CarbonInterface $startsOn,
        public ?int $approvedByUserId = null,
        public string $interestRatePercent = '0',
        /** @var array<int, int>|null */
        public ?array $offsetStudentIds = null,
    ) {}
}
