<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\StockMovement;

final class NegativeStockIssued
{
    public function __construct(
        public readonly StockMovement $movement,
    ) {}
}
