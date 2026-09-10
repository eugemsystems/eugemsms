<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateStaffPayStructureData
{
    public function __construct(
        public int $schoolId,
        public int $staffId,
        public string $primaryCurrency,
        public string $paymentCurrency,
        public CarbonInterface $effectiveFrom,
        public int $approvedByUserId,
        public ?int $contractId = null,
        public ?int $gradeId = null,
        public ?string $notch = null,
    ) {}
}
