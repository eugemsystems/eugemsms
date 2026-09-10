<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\StockLot;

final class StockReceived
{
    public function __construct(
        public readonly StockLot $lot,
    ) {}
}
