<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\StoreRequisition;

final class StockIssued
{
    public function __construct(
        public readonly StoreRequisition $requisition,
    ) {}
}
