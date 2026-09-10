<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\ConfirmBedAllocationData;
use Modules\Boarding\Domain\Events\BedAllocated;
use Modules\Boarding\Models\BedAllocation;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ConfirmBedAllocation (Book F BRD-01 §4/BR-BRD-01-007). The
 * human review step — nothing from `RunBulkAllocationAction` or
 * `AllocateBedAction` (draft mode) becomes real occupancy without a
 * person confirming it here.
 */
final class ConfirmBedAllocationAction extends Action
{
    public function execute(ConfirmBedAllocationData $data): BedAllocation
    {
        $allocation = BedAllocation::findOrFail($data->allocationId);

        if ($allocation->status !== 'draft') {
            throw new InvalidStateTransitionException(
                "Allocation #{$allocation->id} must be draft to be confirmed (currently {$allocation->status}).",
                ['allocation_id' => $allocation->id, 'status' => $allocation->status],
            );
        }

        return $this->transaction(function () use ($allocation, $data): BedAllocation {
            $allocation->update(['status' => 'confirmed', 'confirmed_by' => $data->confirmedByUserId]);

            event(new BedAllocated($allocation));

            return $allocation;
        });
    }
}
