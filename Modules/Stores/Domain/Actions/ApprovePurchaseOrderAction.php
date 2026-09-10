<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Domain\Events\PurchaseOrderApproved;
use Modules\Stores\Models\PurchaseOrder;

/**
 * ACT-ApprovePurchaseOrder (Book H1 FIN-08 §6/BR-FIN-08-009/010 ⭐/
 * AC-FIN-08-004). `CORE-07`'s real multi-step chain isn't wired into
 * any domain module yet anywhere in this codebase — this is the same
 * single-gate boundary `BRD-07`'s `IssueSanctionAction` and `ACA-05`'s
 * `AmendMarkAction` already use for it. `committed_minor` is written
 * here, at approval, never at creation (BR-FIN-08-010) — `FIN-11`
 * doesn't exist yet, so `PurchaseOrderApproved` is the real, fired
 * event a budget-commitment ledger will consume once it does.
 */
final class ApprovePurchaseOrderAction extends Action
{
    public function execute(int $purchaseOrderId, int $approvedByUserId): PurchaseOrder
    {
        $order = PurchaseOrder::findOrFail($purchaseOrderId);
        $order->throwIfNotApprovable();

        if ($order->created_by !== null && $order->created_by === $approvedByUserId) {
            throw new InvalidStateTransitionException(
                'The creator of a purchase order cannot approve it themselves.',
                ['purchase_order_id' => $order->id],
            );
        }

        return $this->transaction(function () use ($order, $approvedByUserId): PurchaseOrder {
            $order->update([
                'status' => 'approved',
                'approved_by' => $approvedByUserId,
                'committed_minor' => $order->base_total_minor,
            ]);

            event(new PurchaseOrderApproved($order));

            return $order;
        });
    }
}
