<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\AcknowledgeEscalationStepData;
use Modules\Boarding\Models\EscalationAction as EscalationActionModel;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-AcknowledgeEscalationStep (Book F BRD-02 §3 ⭐/BR-BRD-02-009/
 * AC-BRD-02-003). Suppresses the notification repeat for this step —
 * nothing else. It never changes `current_step`, never stops the
 * ladder's clock, and is not itself sufficient to satisfy a step
 * flagged `requires_action_record`.
 */
final class AcknowledgeEscalationStepAction extends Action
{
    public function execute(AcknowledgeEscalationStepData $data): EscalationActionModel
    {
        $incident = MissingLearnerIncident::findOrFail($data->incidentId);

        return $this->transaction(fn (): EscalationActionModel => EscalationActionModel::create([
            'school_id' => $incident->school_id,
            'incident_id' => $incident->id,
            'step_number' => $data->stepNumber,
            'action_type' => 'acknowledged',
            'actor_id' => $data->actorId,
            'occurred_at' => Carbon::now(),
        ]));
    }
}
