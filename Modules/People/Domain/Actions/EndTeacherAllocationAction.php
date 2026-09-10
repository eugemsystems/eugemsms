<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\EndTeacherAllocationData;
use Modules\People\Domain\Events\TeacherAllocationEnded;
use Modules\People\Models\TeacherAllocation;

/**
 * ACT-EndTeacherAllocation (Book C PPL-04 §2/BR-PPL-04-008). Ending an
 * allocation recalculates the staff member's workload immediately, so
 * the cache never lags behind the matrix.
 */
final class EndTeacherAllocationAction extends Action
{
    public function __construct(
        private readonly RecalculateStaffWorkloadAction $recalculateWorkload,
    ) {}

    public function execute(EndTeacherAllocationData $data): TeacherAllocation
    {
        $allocation = TeacherAllocation::findOrFail($data->allocationId);

        return $this->transaction(function () use ($allocation, $data): TeacherAllocation {
            $allocation->update([
                'ends_on' => $data->endsOn->toDateString(),
                'status' => $data->status,
            ]);

            $this->recalculateWorkload->execute($allocation->staff_id, $allocation->term_id);

            event(new TeacherAllocationEnded($allocation));

            return $allocation;
        });
    }
}
