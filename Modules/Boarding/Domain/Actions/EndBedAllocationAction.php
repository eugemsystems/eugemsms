<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\EndBedAllocationData;
use Modules\Boarding\Domain\Events\BedReleased;
use Modules\Boarding\Models\BedAllocation;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-EndBedAllocation (Book F BRD-01 §4/BR-BRD-01-005/016/AC-BRD-01-003).
 * A residency change to `DAY`, withdrawal, transfer, or graduation all
 * end the allocation the same way — on the effective date, releasing
 * the bed. A student with no active allocation is a no-op, not an
 * error (ending an allocation that was never confirmed, e.g. for a
 * day scholar, is expected).
 */
final class EndBedAllocationAction extends Action
{
    public function __construct(
        private readonly ReevaluateWaitingListAction $reevaluateWaitingList,
    ) {}

    public function execute(EndBedAllocationData $data): ?BedAllocation
    {
        $allocation = BedAllocation::query()
            ->where('student_id', $data->studentId)
            ->where('status', 'confirmed')
            ->whereNull('effective_to')
            ->first();

        if ($allocation === null) {
            return null;
        }

        $ended = $this->transaction(function () use ($allocation, $data): BedAllocation {
            $allocation->update([
                'status' => 'ended',
                'effective_to' => $data->effectiveTo->toDateString(),
                'reason' => $data->reason,
            ]);

            event(new BedReleased($allocation));

            return $allocation;
        });

        $this->reevaluateWaitingList->execute($ended->school_id, $ended->term_id);

        return $ended;
    }
}
