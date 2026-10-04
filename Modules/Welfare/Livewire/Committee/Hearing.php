<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Committee;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\RecordDisciplinaryCommitteeAction;
use Modules\Welfare\Domain\DataObjects\RecordDisciplinaryCommitteeData;
use Modules\Welfare\Models\DisciplinaryCommittee;

/**
 * `Committee\Hearing` (Book G BRD-07 §5 ⭐, `behaviour.committee.convene`).
 * Panel, statements, findings, minutes. `learnerStatement` is a
 * required field on the DTO — the literal text of a decline is what a
 * caller passes when that's what happened (BR-BRD-07-006).
 */
#[Title('Disciplinary committee')]
#[Layout('layouts.app')]
final class Hearing extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $convenedOn = '';

    public string $panelStaffIdsRaw = '';

    public ?bool $guardianPresent = null;

    public ?bool $learnerPresent = null;

    public string $learnerStatement = '';

    public bool $learnerDeclined = false;

    public ?string $guardianStatement = null;

    public string $findings = '';

    public string $decision = 'no_case';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.committee.convene');

        $this->convenedOn = now()->toDateString();
    }

    public function record(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'convenedOn' => ['required', 'date'],
            'findings' => ['required', 'string'],
            'decision' => ['required', 'string'],
        ]);

        $statement = $this->learnerDeclined ? 'Declined to give a statement.' : $this->learnerStatement;

        if (trim($statement) === '') {
            $this->toast(__('The learner\'s statement, or an explicit note that they declined, is required (BR-BRD-07-006).'), 'danger');

            return;
        }

        $panelIds = array_values(array_filter(array_map('trim', explode(',', $this->panelStaffIdsRaw)), fn (string $v): bool => $v !== ''));

        app(RecordDisciplinaryCommitteeAction::class)->execute(new RecordDisciplinaryCommitteeData(
            schoolId: $this->school->id,
            studentId: (int) $this->studentId,
            convenedOn: Carbon::parse($this->convenedOn),
            panelStaffIds: array_map('intval', $panelIds),
            learnerStatement: $statement,
            findings: $this->findings,
            decision: $this->decision,
            chairedByUserId: (int) Auth::id(),
            guardianPresent: $this->guardianPresent,
            learnerPresent: $this->learnerPresent,
            guardianStatement: $this->guardianStatement,
        ));

        $this->reset(['panelStaffIdsRaw', 'learnerStatement', 'learnerDeclined', 'guardianStatement', 'findings']);
        $this->toast(__('Committee record saved.'));
    }

    public function render(): View
    {
        return view('welfare::committee.hearing', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'hearings' => $this->studentId !== null
                ? DisciplinaryCommittee::where('student_id', $this->studentId)->orderByDesc('id')->get()
                : collect(),
        ]);
    }
}
