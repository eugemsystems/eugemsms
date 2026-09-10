<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\PurchaseOrder;

/**
 * Book H1 FIN-08 §6/BR-FIN-08-010 ⭐. `FIN-11` doesn't exist yet — this
 * event carries everything a real budget-commitment consumer will
 * need (`purchaseOrder.committed_minor`), the same deferral shape as
 * `FIN-09`'s own `ItemCapitalisationDue` for `FIN-10`.
 */
final class PurchaseOrderApproved
{
    public function __construct(
        public readonly PurchaseOrder $purchaseOrder,
    ) {}
}
