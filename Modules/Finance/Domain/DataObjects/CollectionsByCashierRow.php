<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CollectionsByCashierRow
{
    public function __construct(
        public int $cashierId,
        public string $cashierName,
        public string $currency,
        public int $totalMinor,
    ) {}
}
