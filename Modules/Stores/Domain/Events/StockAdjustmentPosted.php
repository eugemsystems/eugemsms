<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\StockTake;

final class StockAdjustmentPosted
{
    public function __construct(
        public readonly StockTake $stockTake,
    ) {}
}
