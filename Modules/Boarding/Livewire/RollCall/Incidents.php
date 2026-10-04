<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\RollCall;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\AcknowledgeEscalationStepAction;
use Modules\Boarding\Domain\Actions\AdvanceEscalationLadderAction;
use Modules\Boarding\Domain\Actions\RecordEscalationActionAction;
use Modules\Boarding\Domain\DataObjects\AcknowledgeEscalationStepData;
use Modules\Boarding\Domain\DataObjects\RecordEscalationActionData;
use Modules\Boarding\Models\EscalationStep;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `RollCall\Incidents` (Book F BRD-02 §3/§6 ⭐⭐, `boarding.incident.view`
 * to browse, `boarding.incident.action` to acknowledge/record). The
 * escalation console. "Advance now" runs `AdvanceEscalationLadderAction`
 * on demand per incident — this codebase has no scheduled-command
 * wiring for it yet (see that action's own docblock: "wiring that
 * schedule entry is a deployment step"), so this button is the honest
 * stand-in until a cron entry exists; the action itself still only
 * ever advances once elapsed time actually passes the next step's
 * `delay_minutes` from `first_missed_at` — clicking early is a no-op.
 * Acknowledging a step never stops the clock (BR-BRD-02-009); a step
 * flagged `requires_action_record` refuses bare acknowledgement and
 * demands free text (BR-BRD-02-010) — `RecordEscalationActionAction`
 * itself enforces this, not a client-side required attribute.
 */
#[Title('Incident console')]
#[Layout('layouts.app')]
final class Incidents extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, string> */
    public array $actionTaken = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.incident.view');
    }

    public function acknowledge(int $incidentId, int $stepNumber): void
    {
        $this->authorizePermission('boarding.incident.action');

        app(AcknowledgeEscalationStepAction::class)->execute(new AcknowledgeEscalationStepData(
            incidentId: $incidentId,
            stepNumber: $stepNumber,
            actorId: (int) Auth::id(),
        ));

        $this->toast(__('Acknowledged — the clock keeps running regardless.'));
    }

    public function recordAction(int $incidentId, int $stepNumber): void
    {
        $this->authorizePermission('boarding.incident.action');

        $text = trim($this->actionTaken[$incidentId] ?? '');

        if ($text === '') {
            $this->toast(__('Describe what you actually checked — acknowledgement alone is not enough.'), 'danger');

            return;
        }

        app(RecordEscalationActionAction::class)->execute(new RecordEscalationActionData(
            incidentId: $incidentId,
            stepNumber: $stepNumber,
            actorId: (int) Auth::id(),
            actionTaken: $text,
        ));

        unset($this->actionTaken[$incidentId]);
        $this->toast(__('Action recorded.'));
    }

    public function advanceNow(int $incidentId): void
    {
        $this->authorizePermission('boarding.incident.action');

        app(AdvanceEscalationLadderAction::class)->execute($incidentId);
        $this->toast(__('Checked — the ladder advances only once its delay has actually elapsed.'));
    }

    public function render(): View
    {
        $incidents = MissingLearnerIncident::where('school_id', $this->school->id)
            ->whereIn('status', ['open', 'escalating'])
            ->with('student', 'actions')
            ->orderBy('first_missed_at')
            ->get();

        $stepsByProfile = EscalationStep::whereIn('profile_id', $incidents->pluck('escalation_profile_id')->filter()->unique())
            ->get()
            ->groupBy('profile_id');

        return view('boarding::rollcall.incidents', [
            'incidents' => $incidents,
            'stepsByProfile' => $stepsByProfile,
        ]);
    }
}
