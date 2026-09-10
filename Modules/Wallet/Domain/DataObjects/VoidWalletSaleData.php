<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\DataObjects;

final readonly class VoidWalletSaleData
{
    public function __construct(
        public int $saleId,
        public string $reason,
        public int $voidedByUserId,
    ) {}
}
