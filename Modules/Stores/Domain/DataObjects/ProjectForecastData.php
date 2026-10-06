<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

final readonly class ProjectForecastData
{
    public function __construct(
        public int $schoolId,
        public int $referenceAcademicYearId,
        public string $currency = 'USD',
        public float $enrolmentGrowthPercent = 0.0,
        public float $feeIncreasePercent = 0.0,
        public ?float $collectionRatePercentOverride = null,
    ) {}
}
