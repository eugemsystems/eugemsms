<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Events;

use Modules\Wallet\Models\WalletTransaction;

final class WalletToppedUp
{
    public function __construct(
        public readonly WalletTransaction $transaction,
    ) {}
}
