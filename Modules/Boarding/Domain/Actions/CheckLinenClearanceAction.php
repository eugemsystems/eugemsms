<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\LinenClearanceResult;
use Modules\Boarding\Models\IssuableItem;
use Modules\Boarding\Models\LearnerIssuedItem;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CheckLinenClearance (Book F BRD-05 §3/BR-BRD-05-002/008/
 * AC-BRD-05-001/004). A standalone, callable check — not wired into
 * `Modules\People\Domain\Actions\WithdrawStudentAction` (Book C
 * PPL-01, already shipped and tested). Any future clearance workflow,
 * or `WithdrawStudentAction` itself if it later grows a final-clearance
 * gate, calls this rather than duplicating the query. An outstanding
 * returnable item — still `issued`, or `damaged`/`lost` pending its
 * own charge approval — blocks clearance; a non-returnable item never
 * does, and a charged item no longer does (it has already been
 * resolved financially).
 */
final class CheckLinenClearanceAction extends Action
{
    public function execute(int $schoolId, int $studentId): LinenClearanceResult
    {
        $returnableItemIds = IssuableItem::query()
            ->where('school_id', $schoolId)
            ->where('is_returnable', true)
            ->pluck('id');

        $outstanding = LearnerIssuedItem::query()
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->whereIn('issuable_item_id', $returnableItemIds)
            ->where(fn ($query) => $query
                ->where('status', 'issued')
                ->orWhere(fn ($q) => $q->whereIn('status', ['damaged', 'lost'])->whereNull('ad_hoc_charge_id')))
            ->pluck('id')
            ->all();

        return new LinenClearanceResult(
            isClear: $outstanding === [],
            outstandingItemIds: $outstanding,
        );
    }
}
