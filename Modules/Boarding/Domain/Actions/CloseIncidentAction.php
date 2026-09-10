<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\CloseIncidentData;
use Modules\Boarding\Domain\Events\IncidentClosed;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-CloseIncident (Book F BRD-02 §3/AC-BRD-02-005). Requires an
 * outcome to already be recorded — a learner must be located before
 * the incident closes. `false_alarm` still closes normally and the
 * row still remains permanently (BR-BRD-02-013's own wording): the
 * pattern of false alarms is itself information.
 */
final class CloseIncidentAction extends Action
{
    public function execute(CloseIncidentData $data): MissingLearnerIncident
    {
        $incident = MissingLearnerIncident::findOrFail($data->incidentId);

        if ($incident->status !== 'located' || $incident->outcome === null) {
            throw new InvalidStateTransitionException(
                "Incident #{$incident->id} requires a recorded outcome before it can be closed.",
                ['incident_id' => $incident->id, 'status' => $incident->status],
            );
        }

        return $this->transaction(function () use ($incident, $data): MissingLearnerIncident {
            $incident->update([
                'status' => 'resolved',
                'closed_by' => $data->closedByUserId,
                'closed_at' => Carbon::now(),
            ]);

            event(new IncidentClosed($incident));

            return $incident;
        });
    }
}
