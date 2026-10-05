<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\DataObjects;

final readonly class CreateReportDefinitionData
{
    /**
     * @param  array<string, mixed>  $structure
     */
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $reportType,
        public array $structure = [],
        public int $comparativePeriods = 1,
        public bool $showVariance = true,
        public bool $showBudget = false,
    ) {}
}
