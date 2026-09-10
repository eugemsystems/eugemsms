<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\StockLot;

final class ExpiryApproaching
{
    public function __construct(
        public readonly StockLot $lot,
        public readonly int $daysRemaining,
    ) {}
}
