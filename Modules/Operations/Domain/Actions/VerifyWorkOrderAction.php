<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Models\WorkOrder;

/**
 * ACT-VerifyWorkOrder (Book H2 OPS-02 §6/BR-OPS-02-012). Only the
 * original requester (`raised_by`) may verify — anyone else must have
 * the request re-raised through them. `sla_met` compares the actual
 * completion against the order's own `target_completion`; an order
 * with no target set is never judged either way.
 */
final class VerifyWorkOrderAction extends Action
{
    public function execute(int $workOrderId, int $verifiedByUserId): WorkOrder
    {
        $workOrder = WorkOrder::findOrFail($workOrderId);

        if ($workOrder->status !== 'completed') {
            throw new InvalidStateTransitionException(
                "Work order #{$workOrder->id} must be completed to verify (currently {$workOrder->status}).",
                ['work_order_id' => $workOrder->id, 'status' => $workOrder->status],
            );
        }

        if ($workOrder->raised_by !== $verifiedByUserId) {
            throw new InvalidStateTransitionException(
                'Only the original requester may verify a work order (BR-OPS-02-012).',
                ['work_order_id' => $workOrder->id, 'raised_by' => $workOrder->raised_by, 'verified_by' => $verifiedByUserId],
            );
        }

        $slaMet = $workOrder->target_completion === null || $workOrder->completed_at === null
            ? null
            : $workOrder->completed_at->lessThanOrEqualTo($workOrder->target_completion->endOfDay());

        return $this->transaction(fn (): WorkOrder => tap($workOrder)->update([
            'status' => 'verified',
            'verified_by' => $verifiedByUserId,
            'verified_at' => now(),
            'sla_met' => $slaMet,
        ]));
    }
}
