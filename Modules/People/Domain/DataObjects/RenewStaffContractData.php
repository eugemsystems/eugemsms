<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RenewStaffContractData
{
    public function __construct(
        public int $contractId,
        public string $contractType,
        public CarbonInterface $startsOn,
        public int $renewedByUserId,
        public ?CarbonInterface $endsOn = null,
        public ?int $probationMonths = null,
        public int $noticePeriodDays = 30,
        public ?string $weeklyHours = null,
        public ?int $basicSalaryMinor = null,
        public ?string $salaryCurrency = null,
        public ?string $salaryGrade = null,
        public ?string $salaryNotch = null,
    ) {}
}
