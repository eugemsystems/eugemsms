<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\DataObjects;

final readonly class CreateWalletProductData
{
    public function __construct(
        public int $schoolId,
        public int $spendPointId,
        public string $code,
        public string $name,
        public string $category,
        public int $priceMinor,
        public string $currency,
        public string $taxType,
        public ?int $itemId = null,
        public ?string $barcode = null,
    ) {}
}
