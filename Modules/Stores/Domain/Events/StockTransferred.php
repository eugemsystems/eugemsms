<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\StockTransfer;

final class StockTransferred
{
    public function __construct(
        public readonly StockTransfer $transfer,
    ) {}
}
