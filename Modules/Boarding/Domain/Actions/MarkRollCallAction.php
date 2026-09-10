<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Boarding\Domain\DataObjects\MarkRollCallData;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallRecord;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Student;

/**
 * ACT-MarkRollCall (Book F BRD-02 §2/§4/BR-BRD-02-003/004/005/006/007/
 * 009 ⭐). The one marking primitive — offline queuing and idempotent
 * replay on reconnect (BR-BRD-02-005/006) both fall out of
 * `updateOrCreate`'s own natural idempotency against the
 * `(roll_call_id, student_id)` unique key: replaying the same mark
 * twice is a no-op, not a duplicate. `roll_date` is always copied
 * from the `RollCall` itself, never accepted from the caller — the
 * server's own date, per BR-BRD-02-007. A `missing` mark opens an
 * incident immediately (BR-BRD-02-009); an incident is never closed
 * by simply re-marking the roll — only `LocateLearnerAction` does that.
 */
final class MarkRollCallAction extends Action
{
    public function __construct(
        private readonly OpenMissingLearnerIncidentAction $openIncident,
    ) {}

    public function execute(MarkRollCallData $data): RollCallRecord
    {
        $rollCall = RollCall::findOrFail($data->rollCallId);

        if ($rollCall->status === 'completed') {
            throw new InvalidStateTransitionException(
                "Roll call #{$rollCall->id} is already completed and cannot be marked further.",
                ['roll_call_id' => $rollCall->id],
            );
        }

        $existing = RollCallRecord::query()
            ->where('roll_call_id', $rollCall->id)
            ->where('student_id', $data->studentId)
            ->first();

        if ($existing !== null && $existing->is_auto_populated && $existing->status !== $data->status && $data->note === null) {
            throw new InvalidArgumentException(
                'Overriding a pre-populated roll call status requires a note (BR-BRD-02-004).',
            );
        }

        $record = $this->transaction(function () use ($rollCall, $data): RollCallRecord {
            $record = RollCallRecord::updateOrCreate(
                ['roll_call_id' => $rollCall->id, 'student_id' => $data->studentId],
                [
                    'school_id' => $rollCall->school_id,
                    'roll_date' => $rollCall->roll_date->toDateString(),
                    'status' => $data->status,
                    'is_auto_populated' => false,
                    'source_reference' => null,
                    'marked_by' => $data->markedByUserId,
                    'marked_at' => Carbon::now(),
                    'device_source' => $data->deviceSource,
                    'note' => $data->note,
                ],
            );

            if ($rollCall->status === 'pending') {
                $rollCall->update(['status' => 'in_progress', 'started_at' => Carbon::now()]);
            }

            $this->recomputeCounts($rollCall);

            return $record;
        });

        if ($data->status === 'missing') {
            $student = Student::findOrFail($data->studentId);
            $this->openIncident->execute($rollCall, $student);
        }

        return $record;
    }

    private function recomputeCounts(RollCall $rollCall): void
    {
        $records = RollCallRecord::query()->where('roll_call_id', $rollCall->id)->get();

        $accountedStatuses = ['exeat', 'sick_bay', 'hospital', 'fixture', 'detention', 'suspended', 'withdrawn'];

        $rollCall->update([
            'present_count' => $records->where('status', 'present')->count(),
            'accounted_count' => $records->whereIn('status', $accountedStatuses)->count(),
            'missing_count' => $records->where('status', 'missing')->count(),
        ]);
    }
}
