<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CreateFeeComponentData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $category,
        public int $incomeAccountId,
        public int $debtorAccountId,
        public string $defaultCurrency,
        public int $createdByUserId,
        public ?string $description = null,
        public ?int $costCentreId = null,
        public bool $isRefundable = false,
        public bool $isMandatory = true,
        public bool $isFiscalisable = false,
        public string $taxCategory = 'exempt',
        public int $allocationPriority = 100,
    ) {}
}
