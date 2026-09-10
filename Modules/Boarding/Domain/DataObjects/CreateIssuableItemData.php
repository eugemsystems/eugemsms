<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CreateIssuableItemData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $category,
        public string $currency,
        public bool $isReturnable = true,
        public bool $isLaunderable = true,
        public ?int $replacementCostMinor = null,
        public ?int $expectedLifespanTerms = null,
        public bool $requiresTagging = false,
        public ?int $inventoryItemId = null,
    ) {}
}
