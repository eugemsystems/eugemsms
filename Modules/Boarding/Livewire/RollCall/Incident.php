<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\RollCall;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CloseIncidentAction;
use Modules\Boarding\Domain\Actions\LocateLearnerAction;
use Modules\Boarding\Domain\DataObjects\CloseIncidentData;
use Modules\Boarding\Domain\DataObjects\LocateLearnerData;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `RollCall\Incident` (Book F BRD-02 §6, `boarding.incident.view` to
 * browse, `boarding.incident.close` ⚠ to close). Full timeline — every
 * notification and action, in order — plus the two terminal steps:
 * locate (halts escalation immediately, BR-BRD-02-012) and close
 * (requires an outcome already recorded; `false_alarm` closes the
 * same way and the row still remains permanently, BR-BRD-02-013).
 * Nothing on this screen offers a delete path, because none exists at
 * any permission level in the Action layer underneath it.
 */
#[Title('Incident detail')]
#[Layout('layouts.app')]
final class Incident extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public MissingLearnerIncident $incident;

    public string $locationFound = '';

    public string $outcome = 'safe';

    public string $outcomeNote = '';

    public function mount(School $school, MissingLearnerIncident $incident): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.incident.view');
        $this->incident = $incident;
    }

    public function locate(): void
    {
        $this->authorizePermission('boarding.incident.action');

        if (trim($this->locationFound) === '') {
            $this->toast(__('Where was the learner found?'), 'danger');

            return;
        }

        app(LocateLearnerAction::class)->execute(new LocateLearnerData(
            incidentId: $this->incident->id,
            locatedByUserId: (int) Auth::id(),
            locationFound: $this->locationFound,
            outcome: $this->outcome,
            outcomeNote: $this->outcomeNote !== '' ? $this->outcomeNote : null,
        ));

        $this->incident->refresh();
        $this->toast(__('Located — escalation halted.'));
    }

    public function close(): void
    {
        $this->authorizePermission('boarding.incident.close');

        try {
            app(CloseIncidentAction::class)->execute(new CloseIncidentData(
                incidentId: $this->incident->id,
                closedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->incident->refresh();
        $this->toast(__('Incident closed — it remains permanently in the record.'));
    }

    public function render(): View
    {
        $this->incident->load('student', 'rollCall.hostel', 'actions');

        return view('boarding::rollcall.incident', [
            'timeline' => $this->incident->actions->sortBy('occurred_at'),
        ]);
    }
}
