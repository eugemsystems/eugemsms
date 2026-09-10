<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\RecordEscalationActionData;
use Modules\Boarding\Domain\Exceptions\ActionRecordRequiredException;
use Modules\Boarding\Models\EscalationAction as EscalationActionModel;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordEscalationAction (Book F BRD-02 §3 ⭐/BR-BRD-02-010/
 * AC-BRD-02-004). The only way a step flagged `requires_action_record`
 * is satisfied — free text describing what was actually checked,
 * never bare acknowledgement.
 */
final class RecordEscalationActionAction extends Action
{
    public function execute(RecordEscalationActionData $data): EscalationActionModel
    {
        $incident = MissingLearnerIncident::findOrFail($data->incidentId);

        if (mb_trim($data->actionTaken) === '') {
            throw ActionRecordRequiredException::forStep($incident->id, $data->stepNumber);
        }

        return $this->transaction(fn (): EscalationActionModel => EscalationActionModel::create([
            'school_id' => $incident->school_id,
            'incident_id' => $incident->id,
            'step_number' => $data->stepNumber,
            'action_type' => 'action_recorded',
            'actor_id' => $data->actorId,
            'action_taken' => $data->actionTaken,
            'occurred_at' => Carbon::now(),
        ]));
    }
}
