<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\DecideMalpracticeOutcomeAction;
use Modules\Academic\Domain\Actions\ReportMalpracticeIncidentAction;
use Modules\Academic\Domain\DataObjects\DecideMalpracticeOutcomeData;
use Modules\Academic\Domain\DataObjects\ReportMalpracticeIncidentData;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\MalpracticeIncident;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Exams\Malpractice` (Book E ACA-07 §4/BR-ACA-07-018/019,
 * `academic.exams.malpractice_view` to list, `academic.exams.malpractice_manage`
 * ⚠ to report/decide). Always confidential — `ReportMalpracticeIncidentAction`'s
 * own docblock says visibility to only the head, deputy, and exams
 * officer "is enforced at the Livewire/API layer, not here" — this
 * screen is that layer: both actions require the dedicated
 * `malpractice_*` permissions rather than the module's general
 * `exams.manage`, so granting the general permission does not expose
 * this list.
 */
#[Title('Malpractice')]
#[Layout('layouts.app')]
final class Malpractice extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $sessionId = null;

    public string $incidentType = 'unauthorised_material';

    public string $description = '';

    public string $occurredAt = '';

    /** @var array<int, string> */
    public array $outcomes = [];

    /** @var array<int, string> */
    public array $investigationNotes = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.malpractice_view');
    }

    public function report(): void
    {
        $this->authorizePermission('academic.exams.malpractice_manage');

        $this->validate([
            'sessionId' => ['required', 'integer'],
            'incidentType' => ['required', 'string'],
            'description' => ['required', 'string', 'min:10'],
            'occurredAt' => ['required', 'date'],
        ]);

        app(ReportMalpracticeIncidentAction::class)->execute(new ReportMalpracticeIncidentData(
            schoolId: $this->school->id,
            sessionId: $this->sessionId,
            incidentType: $this->incidentType,
            description: $this->description,
            reportedByUserId: (int) Auth::id(),
            occurredAt: Carbon::parse($this->occurredAt),
        ));

        $this->reset(['description', 'occurredAt']);
        $this->toast(__('Incident reported — confidential.'));
    }

    public function decide(int $incidentId, string $outcome): void
    {
        $this->authorizePermission('academic.exams.malpractice_manage');

        $notes = trim($this->investigationNotes[$incidentId] ?? '');

        if ($notes === '') {
            $this->toast(__('Investigation notes are required.'), 'danger');

            return;
        }

        app(DecideMalpracticeOutcomeAction::class)->execute(new DecideMalpracticeOutcomeData(
            incidentId: $incidentId,
            outcome: $outcome,
            outcomeByUserId: (int) Auth::id(),
            investigationNotes: $notes,
        ));

        unset($this->investigationNotes[$incidentId]);
        $this->toast(__('Outcome decided.'));
    }

    public function render(): View
    {
        return view('academic::exams.malpractice', [
            'sessions' => ExaminationSession::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'incidents' => MalpracticeIncident::where('school_id', $this->school->id)
                ->with('candidate.student')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
