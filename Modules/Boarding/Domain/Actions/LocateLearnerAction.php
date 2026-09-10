<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\LocateLearnerData;
use Modules\Boarding\Domain\Events\LearnerLocated;
use Modules\Boarding\Models\EscalationAction as EscalationActionModel;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-LocateLearner (Book F BRD-02 §3/BR-BRD-02-012/AC-BRD-02-005).
 * Halts escalation immediately — `status` moves to `located`, which
 * `AdvanceEscalationLadderAction` never advances past. Closing the
 * incident is a separate, explicit step (`CloseIncidentAction`).
 */
final class LocateLearnerAction extends Action
{
    public function execute(LocateLearnerData $data): MissingLearnerIncident
    {
        $incident = MissingLearnerIncident::findOrFail($data->incidentId);

        if (! in_array($incident->status, ['open', 'escalating'], true)) {
            throw new InvalidStateTransitionException(
                "Incident #{$incident->id} is not open/escalating (currently {$incident->status}).",
                ['incident_id' => $incident->id, 'status' => $incident->status],
            );
        }

        return $this->transaction(function () use ($incident, $data): MissingLearnerIncident {
            $incident->update([
                'status' => 'located',
                'located_at' => Carbon::now(),
                'located_by' => $data->locatedByUserId,
                'location_found' => $data->locationFound,
                'outcome' => $data->outcome,
                'outcome_note' => $data->outcomeNote,
            ]);

            EscalationActionModel::create([
                'school_id' => $incident->school_id,
                'incident_id' => $incident->id,
                'step_number' => $incident->current_step,
                'action_type' => 'resolved',
                'actor_id' => $data->locatedByUserId,
                'action_taken' => "Located at {$data->locationFound}: {$data->outcomeNote}",
                'occurred_at' => Carbon::now(),
            ]);

            event(new LearnerLocated($incident));

            return $incident;
        });
    }
}
