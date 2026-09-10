<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockMovement;

/**
 * Book H1 FIN-09 §3/BR-FIN-09-021. Fired when an issued item breaches
 * its capitalisation rule. `FIN-10` (built later in this book) is the
 * real consumer — until it exists, this pass expenses the issue
 * normally (documented, honest deferral) rather than fabricating an
 * asset account that doesn't exist yet, the same "planning-only" shape
 * `BRD-04` used for `FIN-09` itself before this module existed.
 */
final class ItemCapitalisationDue
{
    public function __construct(
        public readonly InventoryItem $item,
        public readonly StockMovement $movement,
        public readonly int $unitCostMinor,
    ) {}
}
