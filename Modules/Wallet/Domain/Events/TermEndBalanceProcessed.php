<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Events;

use Modules\Wallet\Models\StudentWallet;

final class TermEndBalanceProcessed
{
    public function __construct(
        public readonly StudentWallet $wallet,
        public readonly string $policy,
        public readonly int $amountMinor,
    ) {}
}
