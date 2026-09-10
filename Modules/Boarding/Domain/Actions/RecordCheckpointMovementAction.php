<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\RecordCheckpointMovementData;
use Modules\Boarding\Domain\Events\UnauthorisedBoundaryCrossing;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Boarding\Models\MovementLogEntry;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordCheckpointMovement (Book F BRD-02 §2/BR-BRD-02-016/017/
 * AC-BRD-02-008/010). Append-only, and a hardware failure never
 * blocks it — `method: 'manual'` is always available, this action
 * makes no distinction in its own logic between a hardware and a
 * manual scan beyond recording which one happened.
 *
 * `hasActiveExeat` is a flag the caller supplies — `BRD-03` (not
 * built in this pass) is the real source of "is there an approved
 * exeat active right now"; until it exists, every boundary crossing
 * defaults to unauthorised unless the caller explicitly asserts
 * otherwise. That is the SAFE default (fail closed), not a
 * placeholder to "fix later" — it is the correct behaviour with no
 * exeat system to consult.
 */
final class RecordCheckpointMovementAction extends Action
{
    public function execute(RecordCheckpointMovementData $data): MovementLogEntry
    {
        $checkpoint = MovementCheckpoint::findOrFail($data->checkpointId);

        $isAuthorised = ! $checkpoint->is_boundary || $data->hasActiveExeat;

        return $this->transaction(function () use ($checkpoint, $data, $isAuthorised): MovementLogEntry {
            $entry = MovementLogEntry::create([
                'school_id' => $checkpoint->school_id,
                'student_id' => $data->studentId,
                'checkpoint_id' => $checkpoint->id,
                'direction' => $data->direction,
                'occurred_at' => Carbon::now(),
                'method' => $data->method,
                'recorded_by' => $data->recordedByUserId,
                'is_authorised' => $isAuthorised,
                'note' => $data->note,
            ]);

            if (! $isAuthorised) {
                event(new UnauthorisedBoundaryCrossing($entry));
            }

            return $entry;
        });
    }
}
