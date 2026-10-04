<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CreateBankAccountData
{
    public function __construct(
        public int $schoolId,
        public int $glAccountId,
        public string $bankName,
        public string $accountName,
        public string $accountNumber,
        public string $currency,
        public string $accountType,
        public ?string $branch = null,
        public bool $isActive = true,
    ) {}
}
