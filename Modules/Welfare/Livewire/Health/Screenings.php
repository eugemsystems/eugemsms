<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\RecordHealthScreeningAction;
use Modules\Welfare\Domain\DataObjects\RecordHealthScreeningData;
use Modules\Welfare\Models\HealthScreening;

/**
 * `Health\Screenings` (Book G BRD-06 §5, `health.clinical.manage` —
 * Tier 3). `results` is free text here (a single note), matching
 * `RecordHealthScreeningAction`'s own `json_encode` of whatever array
 * the caller passes — kept to one note field rather than a structured
 * per-screening-type form, since the spec names no fixed shape for it.
 */
#[Title('Health screenings')]
#[Layout('layouts.app')]
final class Screenings extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $screeningType = 'vision';

    public string $outcome = 'normal';

    public string $screenedBy = '';

    public ?string $resultsNote = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.clinical.manage');
    }

    public function record(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'screeningType' => ['required', 'string'],
            'outcome' => ['required', 'string'],
            'screenedBy' => ['required', 'string'],
        ]);

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        app(RecordHealthScreeningAction::class)->execute(new RecordHealthScreeningData(
            schoolId: $this->school->id,
            termId: $term->id,
            studentId: (int) $this->studentId,
            screeningType: $this->screeningType,
            screenedOn: Carbon::now(),
            outcome: $this->outcome,
            screenedBy: $this->screenedBy,
            results: $this->resultsNote !== null && $this->resultsNote !== '' ? ['note' => $this->resultsNote] : null,
        ));

        $this->reset(['resultsNote']);
        $this->toast(__('Screening recorded.'));
    }

    public function render(): View
    {
        return view('welfare::health.screenings', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'screenings' => HealthScreening::where('school_id', $this->school->id)->with('student:id,first_name,last_name')->orderByDesc('screened_on')->limit(100)->get(),
        ]);
    }
}
