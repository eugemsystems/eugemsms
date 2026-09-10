<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\StoreRequisitionLine;

final class StockReturned
{
    public function __construct(
        public readonly StoreRequisitionLine $line,
    ) {}
}
