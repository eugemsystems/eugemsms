<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Domain\Events\SupplierApproved;
use Modules\Stores\Models\Supplier;

/**
 * ACT-ApproveSupplier (Book H1 FIN-08 §6/BR-FIN-08-001). Refuses the
 * creator approving their own supplier — the same self-approval guard
 * `Modules\Finance\Domain\Actions\ApproveManualJournalAction` already
 * uses in this codebase.
 */
final class ApproveSupplierAction extends Action
{
    public function execute(int $supplierId, int $approvedByUserId): Supplier
    {
        $supplier = Supplier::findOrFail($supplierId);

        if ($supplier->status !== 'pending_approval') {
            throw new InvalidStateTransitionException(
                "Supplier #{$supplier->id} must be pending approval (currently {$supplier->status}).",
                ['supplier_id' => $supplier->id, 'status' => $supplier->status],
            );
        }

        if ($supplier->created_by !== null && $supplier->created_by === $approvedByUserId) {
            throw new InvalidStateTransitionException(
                'The creator of a supplier record cannot approve it themselves (BR-FIN-08-001).',
                ['supplier_id' => $supplier->id],
            );
        }

        return $this->transaction(function () use ($supplier, $approvedByUserId): Supplier {
            $supplier->update(['status' => 'active', 'approved_by' => $approvedByUserId]);

            event(new SupplierApproved($supplier));

            return $supplier;
        });
    }
}
