<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\AddToWaitingListData;
use Modules\Boarding\Models\HostelWaitingListEntry;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-AddToWaitingList (Book F BRD-01 §4/BR-BRD-01-010). Ordering by
 * `priority_score` and recomputing `position` on release is
 * `ReevaluateWaitingListAction`'s job, not this one — adding to the
 * list never itself allocates a bed.
 */
final class AddToWaitingListAction extends Action
{
    public function execute(AddToWaitingListData $data): HostelWaitingListEntry
    {
        return $this->transaction(fn (): HostelWaitingListEntry => HostelWaitingListEntry::updateOrCreate(
            ['school_id' => $data->schoolId, 'term_id' => $data->termId, 'student_id' => $data->studentId],
            [
                'academic_year_id' => $data->academicYearId,
                'preferred_hostel_id' => $data->preferredHostelId,
                'priority_score' => $data->priorityScore,
                'reason' => $data->reason,
                'status' => 'waiting',
                'added_at' => Carbon::now(),
            ],
        ));
    }
}
