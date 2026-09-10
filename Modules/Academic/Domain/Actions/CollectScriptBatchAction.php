<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\CollectScriptBatchData;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ScriptBatch;
use Modules\Academic\Models\ScriptCustodyLogEntry;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CollectScriptBatch (Book E ACA-07 §3 ⭐/BR-ACA-07-012). Opens a
 * batch's chain of custody — the first append-only log entry, with no
 * `from_staff_id` (the scripts come from the exam room itself, not a
 * prior holder).
 */
final class CollectScriptBatchAction extends Action
{
    public function execute(CollectScriptBatchData $data): ScriptBatch
    {
        $paper = ExaminationPaper::findOrFail($data->paperId);

        return $this->transaction(function () use ($paper, $data): ScriptBatch {
            $batch = ScriptBatch::create([
                'school_id' => $paper->school_id,
                'paper_id' => $paper->id,
                'batch_reference' => 'BATCH-'.now()->format('YmdHis').'-'.$paper->id,
                'venue_id' => $data->venueId,
                'script_count' => $data->scriptCount,
                'expected_count' => $data->expectedCount,
                'status' => $data->scriptCount === $data->expectedCount ? 'collected' : 'discrepancy',
                'current_holder_staff_id' => $data->collectedByStaffId,
            ]);

            ScriptCustodyLogEntry::create([
                'school_id' => $batch->school_id,
                'batch_id' => $batch->id,
                'from_staff_id' => null,
                'to_staff_id' => $data->collectedByStaffId,
                'action' => 'collected',
                'script_count' => $data->scriptCount,
                'discrepancy_note' => $data->scriptCount !== $data->expectedCount
                    ? "Expected {$data->expectedCount}, collected {$data->scriptCount}."
                    : null,
                'occurred_at' => Carbon::now(),
                'recorded_by' => $data->recordedByUserId,
            ]);

            return $batch;
        });
    }
}
