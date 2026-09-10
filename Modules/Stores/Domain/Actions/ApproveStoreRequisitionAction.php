<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Models\StoreRequisition;

/**
 * ACT-ApproveStoreRequisition (Book H1 FIN-09 §2). Approves every line
 * at its requested quantity unless the caller supplies overrides —
 * partial approval is expressed by passing a smaller quantity for that
 * line's id.
 */
final class ApproveStoreRequisitionAction extends Action
{
    /**
     * @param  array<int, float>  $lineQuantityOverrides  lineId => approvedQuantity
     */
    public function execute(int $requisitionId, int $approvedByUserId, array $lineQuantityOverrides = []): StoreRequisition
    {
        $requisition = StoreRequisition::with('lines')->findOrFail($requisitionId);

        if ($requisition->status !== 'pending') {
            throw new InvalidStateTransitionException(
                "Requisition #{$requisition->id} must be pending to approve (currently {$requisition->status}).",
                ['requisition_id' => $requisition->id, 'status' => $requisition->status],
            );
        }

        return $this->transaction(function () use ($requisition, $approvedByUserId, $lineQuantityOverrides): StoreRequisition {
            foreach ($requisition->lines as $line) {
                $line->update([
                    'quantity_approved' => $lineQuantityOverrides[$line->id] ?? $line->quantity_requested,
                ]);
            }

            $requisition->update([
                'status' => 'approved',
                'approved_by' => $approvedByUserId,
            ]);

            return $requisition;
        });
    }
}
