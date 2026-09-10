<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Modules\Core\Domain\Support\Money;

final readonly class ConvertedAmount
{
    public function __construct(
        public Money $amount,
        public string $rate,
        public ?int $exchangeRateId = null,
    ) {}
}
