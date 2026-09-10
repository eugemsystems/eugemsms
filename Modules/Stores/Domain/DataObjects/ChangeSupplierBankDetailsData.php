<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

final readonly class ChangeSupplierBankDetailsData
{
    public function __construct(
        public int $supplierId,
        public string $bankName,
        public string $bankBranch,
        public string $accountNumber,
        public string $accountName,
        public int $requestedByUserId,
        public int $approvedByUserId,
        public ?string $swiftCode = null,
    ) {}
}
