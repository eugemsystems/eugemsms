<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

final readonly class CreateForecastData
{
    /**
     * @param  array<string, mixed>  $assumptions
     * @param  array<string, mixed>  $projections
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $forecastType,
        public string $scenarioName,
        public array $assumptions,
        public array $projections,
        public int $generatedByUserId,
        public bool $isBaseline = false,
    ) {}
}
