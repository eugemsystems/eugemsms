<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CalculateSubledgerBalanceData
{
    public function __construct(
        public int $schoolId,
        public string $subledgerType,
        public int $subledgerId,
        public string $currency,
        public CarbonInterface $asAt,
        public ?int $accountId = null,
    ) {}
}
