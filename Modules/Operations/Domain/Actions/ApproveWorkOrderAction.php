<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Models\WorkOrder;

/**
 * ACT-ApproveWorkOrder (Book H2 OPS-02 §6/BR-OPS-02-004). Only a work
 * order `CreateWorkOrderAction` left `pending_approval` (estimated
 * cost at or above the configured threshold) needs this — everything
 * else already starts `approved`.
 */
final class ApproveWorkOrderAction extends Action
{
    public function execute(int $workOrderId, int $approvedByUserId): WorkOrder
    {
        $workOrder = WorkOrder::findOrFail($workOrderId);

        if ($workOrder->status !== 'pending_approval') {
            throw new InvalidStateTransitionException(
                "Work order #{$workOrder->id} must be pending approval (currently {$workOrder->status}).",
                ['work_order_id' => $workOrder->id, 'status' => $workOrder->status],
            );
        }

        return $this->transaction(fn (): WorkOrder => tap($workOrder)->update([
            'status' => 'approved',
            'approved_by' => $approvedByUserId,
        ]));
    }
}
