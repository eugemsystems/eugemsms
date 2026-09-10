<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\DataObjects;

final readonly class TopUpWalletData
{
    public function __construct(
        public int $walletId,
        public int $academicYearId,
        public int $termId,
        public int $amountMinor,
        public int $clearingAccountId,
        public int $performedByUserId,
        public ?int $receiptId = null,
    ) {}
}
