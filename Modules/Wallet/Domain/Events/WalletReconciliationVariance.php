<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Events;

final class WalletReconciliationVariance
{
    public function __construct(
        public readonly int $schoolId,
        public readonly int $sumOfBalancesMinor,
        public readonly int $liabilityAccountBalanceMinor,
    ) {}
}
