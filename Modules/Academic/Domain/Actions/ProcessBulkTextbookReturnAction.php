<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\ProcessBulkTextbookReturnData;
use Modules\Academic\Models\BulkTextbookIssue;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;

/**
 * ACT-ProcessBulkTextbookReturn (Book K ACA-10 §3 ⭐/BR-ACA-10-007/008
 * ⭐/AC-ACA-10-002). Reconciles what was scanned back
 * (`returnedItemIdsByStudent`) against each learner's own active
 * bulk-issued loans; anything not scanned back is presumed lost and
 * charges the learner through `FIN-02`, exactly like a single-loan
 * loss report. BR-ACA-10-008's clearance-gate feed is
 * `CheckLibraryClearanceAction` — a standalone check, not wired into
 * `WithdrawStudentAction` here, same as `BRD-05`'s own
 * `CheckLinenClearanceAction`.
 */
final class ProcessBulkTextbookReturnAction extends Action
{
    public function __construct(
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    public function execute(ProcessBulkTextbookReturnData $data): BulkTextbookIssue
    {
        $studentIds = ClassAllocation::query()
            ->where('school_id', $data->schoolId)
            ->where('term_id', $data->termId)
            ->where('class_id', $data->classId)
            ->where('status', 'confirmed')
            ->pluck('student_id');

        return $this->transaction(function () use ($data, $studentIds): BulkTextbookIssue {
            $exceptions = [];
            $completedCount = 0;

            foreach ($studentIds as $studentId) {
                $studentHadException = false;
                $returnedItemIds = $data->returnedItemIdsByStudent[$studentId] ?? [];

                foreach ($data->itemIds as $itemId) {
                    $loan = Loan::query()
                        ->where('school_id', $data->schoolId)
                        ->where('borrower_type', 'student')
                        ->where('borrower_id', $studentId)
                        ->where('status', 'active')
                        ->whereHas('copy', fn ($q) => $q->where('item_id', $itemId))
                        ->with('copy.item')
                        ->first();

                    if ($loan === null) {
                        continue;
                    }

                    if (in_array($itemId, $returnedItemIds, true)) {
                        $loan->update(['status' => 'returned', 'returned_on' => Carbon::today()]);
                        $loan->copy->update(['status' => 'available']);

                        continue;
                    }

                    $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                        schoolId: $data->schoolId,
                        academicYearId: $loan->term->academic_year_id,
                        termId: $data->termId,
                        studentId: $studentId,
                        componentId: $data->feeComponentId,
                        description: "Unreturned bulk-issued textbook: {$loan->copy->item->title}",
                        unitRateMinor: (int) ($loan->copy->item->replacement_cost_minor ?? 0),
                        currency: $loan->copy->item->currency ?? 'USD',
                        raisedByUserId: $data->chargedByUserId,
                        sourceType: 'loan',
                        sourceId: $loan->id,
                        approvedByUserId: $data->chargedByUserId,
                    ));

                    $loan->update(['status' => 'lost', 'fine_charge_id' => $charge->id]);
                    $loan->copy->update(['status' => 'lost']);

                    $exceptions[] = ['student_id' => $studentId, 'item_id' => $itemId, 'reason' => 'not_returned'];
                    $studentHadException = true;
                }

                if (! $studentHadException) {
                    $completedCount++;
                }
            }

            return BulkTextbookIssue::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'class_id' => $data->classId,
                'issue_type' => 'term_end_return',
                'item_ids' => $data->itemIds,
                'total_learners' => $studentIds->count(),
                'completed_count' => $completedCount,
                'exception_count' => $studentIds->count() - $completedCount,
                'exceptions' => $exceptions === [] ? null : $exceptions,
                'status' => 'completed',
            ]);
        });
    }
}
