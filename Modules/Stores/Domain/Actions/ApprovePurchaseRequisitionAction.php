<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Domain\Events\RequisitionApproved;
use Modules\Stores\Models\PurchaseRequisition;

final class ApprovePurchaseRequisitionAction extends Action
{
    public function execute(int $requisitionId, int $approvedByUserId): PurchaseRequisition
    {
        $requisition = PurchaseRequisition::findOrFail($requisitionId);

        if ($requisition->status !== 'pending') {
            throw new InvalidStateTransitionException(
                "Requisition #{$requisition->id} must be pending to approve (currently {$requisition->status}).",
                ['requisition_id' => $requisition->id, 'status' => $requisition->status],
            );
        }

        if ($requisition->requested_by === $approvedByUserId) {
            throw new InvalidStateTransitionException(
                'The requester cannot approve their own purchase requisition.',
                ['requisition_id' => $requisition->id],
            );
        }

        return $this->transaction(function () use ($requisition, $approvedByUserId): PurchaseRequisition {
            $requisition->update(['status' => 'approved', 'approved_by' => $approvedByUserId]);

            event(new RequisitionApproved($requisition));

            return $requisition;
        });
    }
}
