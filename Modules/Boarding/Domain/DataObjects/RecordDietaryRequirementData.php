<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordDietaryRequirementData
{
    /**
     * @param  array<int, string>|null  $allergens
     * @param  array<int, string>|null  $excludedItems
     */
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $requirementType,
        public string $severity,
        public string $description,
        public CarbonInterface $effectiveFrom,
        public ?array $allergens = null,
        public ?array $excludedItems = null,
        public ?string $alternativeProvision = null,
        public ?int $medicalSourceId = null,
        public bool $requiresEpipen = false,
    ) {}
}
