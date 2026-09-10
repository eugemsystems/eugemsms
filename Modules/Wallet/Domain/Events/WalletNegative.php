<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Events;

use Modules\Wallet\Models\StudentWallet;

final class WalletNegative
{
    public function __construct(
        public readonly StudentWallet $wallet,
    ) {}
}
