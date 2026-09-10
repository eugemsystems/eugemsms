<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CreateCostCentreData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public ?int $parentId = null,
        public ?int $sectionId = null,
        public ?int $managerUserId = null,
        public bool $isProfitCentre = false,
    ) {}
}
