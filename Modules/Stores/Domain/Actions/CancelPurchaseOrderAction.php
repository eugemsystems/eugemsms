<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\VoidAllocatedNumberAction;
use Modules\Core\Domain\DataObjects\Documents\VoidAllocatedNumberData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\AllocatedNumber;
use Modules\Stores\Models\PurchaseOrder;

/**
 * ACT-CancelPurchaseOrder (Book H1 FIN-08 §6/BR-FIN-08-011/FIN-11
 * BR-FIN-11-006). A cancelled order voids its own gapless number with
 * a reason (`CORE-06`) rather than leaving a silently-abandoned
 * sequence gap unexplained in the gap report, and releases whatever
 * budget commitment is still outstanding against it — a no-op when
 * the order never carried one.
 */
final class CancelPurchaseOrderAction extends Action
{
    public function __construct(
        private readonly VoidAllocatedNumberAction $voidNumber,
        private readonly ReleaseCommitmentAction $releaseCommitment,
    ) {}

    public function execute(int $purchaseOrderId, string $reason, int $cancelledByUserId): PurchaseOrder
    {
        $order = PurchaseOrder::findOrFail($purchaseOrderId);

        if (in_array($order->status, ['received', 'invoiced', 'closed', 'cancelled'], true)) {
            throw new InvalidStateTransitionException(
                "Purchase order #{$order->id} cannot be cancelled from status {$order->status}.",
                ['purchase_order_id' => $order->id, 'status' => $order->status],
            );
        }

        $allocatedNumber = AllocatedNumber::withoutGlobalScopes()
            ->where('school_id', $order->school_id)
            ->where('document_type', 'purchase_order')
            ->where('formatted_number', $order->po_number)
            ->first();

        return $this->transaction(function () use ($order, $reason, $cancelledByUserId, $allocatedNumber): PurchaseOrder {
            if ($allocatedNumber !== null) {
                $this->voidNumber->execute(new VoidAllocatedNumberData(
                    allocatedNumberId: $allocatedNumber->id,
                    reason: $reason,
                    voidedByUserId: $cancelledByUserId,
                ));
            }

            $order->update(['status' => 'cancelled', 'notes' => $reason]);

            $this->releaseCommitment->execute('purchase_order', $order->id);

            return $order;
        });
    }
}
