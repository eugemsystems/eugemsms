<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreatePaymentPlanData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $partyType,
        public int $partyId,
        public int $totalMinor,
        public string $currency,
        public int $instalmentCount,
        public CarbonInterface $firstDueDate,
        public int $createdByUserId,
    ) {}
}
