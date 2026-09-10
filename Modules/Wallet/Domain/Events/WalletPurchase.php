<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Events;

use Modules\Wallet\Models\WalletSale;

final class WalletPurchase
{
    public function __construct(
        public readonly WalletSale $sale,
    ) {}
}
