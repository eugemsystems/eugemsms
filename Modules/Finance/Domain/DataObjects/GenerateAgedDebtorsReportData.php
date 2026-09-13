<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GenerateAgedDebtorsReportData
{
    public function __construct(
        public int $schoolId,
        public string $currency,
        public CarbonInterface $asAt,
        public ?int $sectionId = null,
        public ?int $gradeLevelId = null,
        public ?string $residency = null,
    ) {}
}
