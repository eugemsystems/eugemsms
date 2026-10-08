<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GenerateKeyFinancialRatiosData
{
    public function __construct(
        public int $schoolId,
        public CarbonInterface $periodStart,
        public CarbonInterface $periodEnd,
        public string $currency,
    ) {}
}
