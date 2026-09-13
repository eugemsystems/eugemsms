<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class GenerateDailyBankingReportData
{
    public function __construct(
        public int $schoolId,
        public string $date,
    ) {}
}
