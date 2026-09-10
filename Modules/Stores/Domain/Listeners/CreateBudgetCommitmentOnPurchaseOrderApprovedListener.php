<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Listeners;

use Modules\Stores\Domain\Actions\CreateBudgetCommitmentAction;
use Modules\Stores\Domain\Events\PurchaseOrderApproved;

/**
 * Book H1 FIN-11 §6 ⭐/BR-FIN-11-004/AC-FIN-11-001 — the real consumer
 * of `FIN-08`'s `PurchaseOrderApproved`, closing that event's own
 * documented "fires but nothing listens yet" deferral. A no-op for a
 * PO that was never tied to a budget line in the first place.
 */
final class CreateBudgetCommitmentOnPurchaseOrderApprovedListener
{
    public function __construct(
        private readonly CreateBudgetCommitmentAction $createCommitment,
    ) {}

    public function handle(PurchaseOrderApproved $event): void
    {
        $order = $event->purchaseOrder;

        if ($order->budget_line_id === null) {
            return;
        }

        $this->createCommitment->execute($order->budget_line_id, 'purchase_order', $order->id, $order->committed_minor);
    }
}
