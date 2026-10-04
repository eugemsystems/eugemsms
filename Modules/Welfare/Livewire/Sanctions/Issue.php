<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Sanctions;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\IssueSanctionAction;
use Modules\Welfare\Domain\DataObjects\IssueSanctionData;
use Modules\Welfare\Models\BehaviourRecord;
use Modules\Welfare\Models\DisciplinaryCommittee;
use Modules\Welfare\Models\SanctionType;

/**
 * `Sanctions\Issue` (Book G BRD-07 §5 ⭐, `behaviour.sanction.issue`).
 * Linked records, reason, duration, guardian notification preview.
 * Every refusal surfaced here — paused safeguarding records, a missing
 * committee record, a boarder's missing supervision arrangements — is
 * `IssueSanctionAction`'s own refusal, not a client-side guess.
 */
#[Title('Issue sanction')]
#[Layout('layouts.app')]
final class Issue extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public ?int $sanctionTypeId = null;

    /** @var array<int, int> */
    public array $behaviourRecordIds = [];

    public string $reason = '';

    public string $startsOn = '';

    public ?string $endsOn = null;

    public ?int $durationDays = null;

    public ?int $committeeRecordId = null;

    public ?string $boardingArrangements = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.sanction.issue');

        $this->startsOn = now()->toDateString();
    }

    public function issue(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'sanctionTypeId' => ['required', 'integer'],
            'reason' => ['required', 'string'],
            'startsOn' => ['required', 'date'],
        ]);

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        try {
            app(IssueSanctionAction::class)->execute(new IssueSanctionData(
                schoolId: $this->school->id,
                academicYearId: (int) $term->academic_year_id,
                termId: $term->id,
                studentId: (int) $this->studentId,
                sanctionTypeId: (int) $this->sanctionTypeId,
                behaviourRecordIds: $this->behaviourRecordIds,
                reason: $this->reason,
                startsOn: Carbon::parse($this->startsOn),
                issuedByUserId: (int) Auth::id(),
                endsOn: $this->endsOn !== null && $this->endsOn !== '' ? Carbon::parse($this->endsOn) : null,
                durationDays: $this->durationDays,
                committeeRecordId: $this->committeeRecordId,
                boardingArrangements: $this->boardingArrangements,
            ));
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->reset(['reason', 'endsOn', 'durationDays', 'committeeRecordId', 'boardingArrangements', 'behaviourRecordIds']);
        $this->toast(__('Sanction issued.'));
    }

    public function render(): View
    {
        return view('welfare::sanctions.issue', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name', 'residency']),
            'sanctionTypes' => SanctionType::where('school_id', $this->school->id)->where('is_active', true)->orderBy('severity_level')->get(),
            'records' => $this->studentId !== null
                ? BehaviourRecord::where('student_id', $this->studentId)->where('polarity', 'negative')->orderByDesc('occurred_at')->limit(30)->get()
                : collect(),
            'committees' => $this->studentId !== null
                ? DisciplinaryCommittee::where('student_id', $this->studentId)->orderByDesc('id')->get()
                : collect(),
        ]);
    }
}
