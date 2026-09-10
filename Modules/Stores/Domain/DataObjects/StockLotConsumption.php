<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Modules\Stores\Models\StockLot;

final readonly class StockLotConsumption
{
    public function __construct(
        public StockLot $lot,
        public float $quantity,
        public int $costMinor,
    ) {}
}
