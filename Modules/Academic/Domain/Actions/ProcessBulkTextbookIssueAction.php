<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\ProcessBulkTextbookIssueData;
use Modules\Academic\Models\BulkTextbookIssue;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ProcessBulkTextbookIssue (Book K ACA-10 §3 ⭐/BR-ACA-10-007/
 * AC-ACA-10-001). Processes the whole class roster as one operation;
 * a missing copy for one learner never blocks the rest of the batch —
 * it's recorded as an exception, one JSON entry per (student, item)
 * incident, on the `exceptions` column this migration added. A
 * student is only counted in `completed_count` if every item in the
 * set was actually issued to them.
 */
final class ProcessBulkTextbookIssueAction extends Action
{
    public function execute(ProcessBulkTextbookIssueData $data): BulkTextbookIssue
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

                foreach ($data->itemIds as $itemId) {
                    $copy = LibraryCopy::query()
                        ->where('school_id', $data->schoolId)
                        ->where('item_id', $itemId)
                        ->where('status', 'available')
                        ->lockForUpdate()
                        ->first();

                    if ($copy === null) {
                        $exceptions[] = ['student_id' => $studentId, 'item_id' => $itemId, 'reason' => 'no_available_copy'];
                        $studentHadException = true;

                        continue;
                    }

                    $copy->update(['status' => 'on_loan']);

                    Loan::create([
                        'school_id' => $data->schoolId,
                        'term_id' => $data->termId,
                        'copy_id' => $copy->id,
                        'borrower_type' => 'student',
                        'borrower_id' => $studentId,
                        'borrower_category' => 'secondary',
                        'issued_on' => Carbon::today(),
                        'due_on' => Carbon::today()->addMonths(4),
                        'renewal_count' => 0,
                        'status' => 'active',
                    ]);
                }

                if (! $studentHadException) {
                    $completedCount++;
                }
            }

            return BulkTextbookIssue::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'class_id' => $data->classId,
                'issue_type' => 'term_start_issue',
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
