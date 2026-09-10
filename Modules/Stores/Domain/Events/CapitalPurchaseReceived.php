<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\GrnLine;

/**
 * Book H1 FIN-08 §2/§3 — the `FIN-10` boundary. A `purchase_order_lines
 * .is_capital` line still receives normally; this event carries what a
 * real `FIN-10` asset-capitalisation consumer will need once that
 * module exists, matching `FIN-09`'s own `ItemCapitalisationDue`
 * deferral shape exactly.
 */
final class CapitalPurchaseReceived
{
    public function __construct(
        public readonly GrnLine $grnLine,
        public readonly int $unitCostMinor,
    ) {}
}
