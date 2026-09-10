<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\DataObjects;

final readonly class ProcessTermEndWalletData
{
    public function __construct(
        public int $walletId,
        public int $academicYearId,
        public int $termId,
        public string $policy,
        public int $performedByUserId,
        public ?int $refundClearingAccountId = null,
        public ?int $feeDebtorsAccountId = null,
    ) {}
}
