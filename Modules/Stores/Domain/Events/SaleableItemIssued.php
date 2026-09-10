<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\StockMovement;

final class SaleableItemIssued
{
    public function __construct(
        public readonly StockMovement $movement,
        public readonly int $adHocChargeId,
    ) {}
}
