<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Models\PurchaseOrder;

/**
 * ACT-ClosePurchaseOrderShort (Book H1 FIN-08 §6/BR-FIN-08-013/FIN-11
 * BR-FIN-11-006/AC-FIN-11-003). A partially-received order that will
 * never be fully delivered is closed short rather than left open
 * forever — this is what actually releases the residual commitment
 * still sitting against it (AC-FIN-11-003's "the order is then closed
 * short").
 */
final class ClosePurchaseOrderShortAction extends Action
{
    public function __construct(
        private readonly ReleaseCommitmentAction $releaseCommitment,
    ) {}

    public function execute(int $purchaseOrderId, string $reason, int $performedByUserId): PurchaseOrder
    {
        $order = PurchaseOrder::findOrFail($purchaseOrderId);

        if (! in_array($order->status, ['partially_received', 'received', 'invoiced'], true)) {
            throw new InvalidStateTransitionException(
                "Purchase order #{$order->id} cannot be closed short from status {$order->status}.",
                ['purchase_order_id' => $order->id, 'status' => $order->status],
            );
        }

        return $this->transaction(function () use ($order, $reason): PurchaseOrder {
            $order->update(['status' => 'closed', 'notes' => $reason]);

            $this->releaseCommitment->execute('purchase_order', $order->id);

            return $order;
        });
    }
}
