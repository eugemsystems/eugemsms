<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Models\EscalationStep;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-AdvanceEscalationLadder (Book F BRD-02 §3 ⭐⭐/BR-BRD-02-009/
 * AC-BRD-02-003). The ladder advances automatically on elapsed time,
 * never on acknowledgement — this action is meant to run on a short
 * schedule (every minute or so) against every `open`/`escalating`
 * incident; wiring that schedule entry is a deployment step (this
 * pass builds the action itself, which is directly and deterministically
 * testable against a fixed clock). A step's `delay_minutes` counts
 * from `first_missed_at`, not from the previous step's own trigger
 * time, matching the ladder diagram's own "T+0", "T+10", "T+20"
 * labelling (absolute offsets from the first miss). Works identically
 * whether the incident came from a roll call or an overdue exeat
 * (BRD-03) — both resolve their profile once, at open time, onto
 * `escalation_profile_id`.
 */
final class AdvanceEscalationLadderAction extends Action
{
    public function __construct(
        private readonly OpenMissingLearnerIncidentAction $openIncident,
    ) {}

    public function execute(int $incidentId): MissingLearnerIncident
    {
        $incident = MissingLearnerIncident::findOrFail($incidentId);

        if (! in_array($incident->status, ['open', 'escalating'], true)) {
            return $incident;
        }

        if ($incident->escalation_profile_id === null) {
            return $incident;
        }

        $nextStepNumber = $incident->current_step + 1;
        $nextStep = EscalationStep::query()
            ->where('profile_id', $incident->escalation_profile_id)
            ->where('step_number', $nextStepNumber)
            ->first();

        if ($nextStep === null) {
            return $incident;
        }

        $dueAt = $incident->first_missed_at->copy()->addMinutes($nextStep->delay_minutes);

        if (Carbon::now()->lessThan($dueAt)) {
            return $incident;
        }

        return $this->transaction(function () use ($incident, $nextStep): MissingLearnerIncident {
            $incident->update(['current_step' => $nextStep->step_number, 'status' => 'escalating']);

            $this->openIncident->triggerStep($incident, $nextStep->step_number);

            return $incident;
        });
    }
}
