<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class UpdateBankAccountData
{
    public function __construct(
        public int $bankAccountId,
        public int $glAccountId,
        public string $bankName,
        public string $accountName,
        public string $accountNumber,
        public string $currency,
        public string $accountType,
        public ?string $branch,
        public bool $isActive,
    ) {}
}
