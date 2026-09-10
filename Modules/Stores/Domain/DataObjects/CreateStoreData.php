<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

final readonly class CreateStoreData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $storeType,
        public int $costCentreId,
        public int $inventoryAccountId,
        public int $defaultExpenseAccountId,
        public string $costingMethod = 'fifo',
        public ?int $custodianStaffId = null,
        public ?string $location = null,
        public bool $requiresIssueApproval = false,
        public bool $allowsNegativeStock = false,
        public ?int $createdByUserId = null,
    ) {}
}
