<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\HandoverScriptBatchData;
use Modules\Academic\Domain\Events\ScriptDiscrepancyDetected;
use Modules\Academic\Models\ScriptBatch;
use Modules\Academic\Models\ScriptCustodyLogEntry;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-HandoverScriptBatch (Book E ACA-07 §3 ⭐/BR-ACA-07-012/AC-ACA-07-004).
 * Every handover writes an append-only custody entry with both
 * parties and the count, before the batch's own denormalised
 * `current_holder_staff_id`/`status` ever change. A count mismatch
 * against the batch's `expected_count` sets `discrepancy` immediately
 * and fires `ScriptDiscrepancyDetected` — never silently corrected or
 * averaged away.
 */
final class HandoverScriptBatchAction extends Action
{
    public function execute(HandoverScriptBatchData $data): ScriptBatch
    {
        $batch = ScriptBatch::findOrFail($data->batchId);

        if (in_array($batch->status, ['archived'], true)) {
            throw new InvalidStateTransitionException(
                "Batch #{$batch->id} is archived and cannot be handed over further.",
                ['batch_id' => $batch->id, 'status' => $batch->status],
            );
        }

        $hasDiscrepancy = $data->scriptCount !== $batch->expected_count;

        return $this->transaction(function () use ($batch, $data, $hasDiscrepancy): ScriptBatch {
            $entry = ScriptCustodyLogEntry::create([
                'school_id' => $batch->school_id,
                'batch_id' => $batch->id,
                'from_staff_id' => $batch->current_holder_staff_id,
                'to_staff_id' => $data->toStaffId,
                'action' => $data->action,
                'script_count' => $data->scriptCount,
                'discrepancy_note' => $hasDiscrepancy
                    ? "Expected {$batch->expected_count}, received {$data->scriptCount}."
                    : $data->discrepancyNote,
                'occurred_at' => Carbon::now(),
                'recorded_by' => $data->recordedByUserId,
            ]);

            $batch->update([
                'current_holder_staff_id' => $data->toStaffId,
                'script_count' => $data->scriptCount,
                'status' => $hasDiscrepancy ? 'discrepancy' : $data->action,
            ]);

            if ($hasDiscrepancy) {
                event(new ScriptDiscrepancyDetected($batch, $entry));
            }

            return $batch;
        });
    }
}
