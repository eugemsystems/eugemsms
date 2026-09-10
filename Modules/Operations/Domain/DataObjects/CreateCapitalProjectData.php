<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateCapitalProjectData
{
    public function __construct(
        public int $schoolId,
        public string $name,
        public int $budgetMinor,
        public string $currency,
        public CarbonInterface $startsOn,
        public int $createdByUserId,
        public ?string $description = null,
        public ?int $budgetLineId = null,
        public ?int $assetCategoryId = null,
        public ?CarbonInterface $targetCompletion = null,
        public ?int $projectManagerId = null,
        public ?int $mainContractorId = null,
        public bool $capitaliseOnCompletion = true,
    ) {}
}
