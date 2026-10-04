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
use Modules\Welfare\Domain\Actions\RecordImmunisationAction;
use Modules\Welfare\Domain\DataObjects\RecordImmunisationData;
use Modules\Welfare\Models\Immunisation;

/**
 * `Health\Immunisations` (Book G BRD-06 §5, `health.clinical.view` to
 * browse, `health.clinical.manage` to record — Tier 3).
 */
#[Title('Immunisations')]
#[Layout('layouts.app')]
final class Immunisations extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $vaccine = '';

    public string $status = 'recorded';

    public ?int $doseNumber = null;

    public ?string $administeredOn = null;

    public ?string $administeredBy = null;

    public ?string $nextDueOn = null;

    public ?string $declineReason = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.clinical.view');
    }

    public function record(): void
    {
        $this->authorizePermission('health.clinical.manage');

        $this->validate([
            'studentId' => ['required', 'integer'],
            'vaccine' => ['required', 'string'],
            'status' => ['required', 'string'],
        ]);

        app(RecordImmunisationAction::class)->execute(new RecordImmunisationData(
            schoolId: $this->school->id,
            studentId: (int) $this->studentId,
            vaccine: $this->vaccine,
            status: $this->status,
            doseNumber: $this->doseNumber,
            administeredOn: $this->administeredOn !== null && $this->administeredOn !== '' ? Carbon::parse($this->administeredOn) : null,
            administeredBy: $this->administeredBy,
            nextDueOn: $this->nextDueOn !== null && $this->nextDueOn !== '' ? Carbon::parse($this->nextDueOn) : null,
            declineReason: $this->declineReason,
        ));

        $this->reset(['vaccine', 'doseNumber', 'administeredOn', 'administeredBy', 'nextDueOn', 'declineReason']);
        $this->toast(__('Immunisation recorded.'));
    }

    public function render(): View
    {
        return view('welfare::health.immunisations', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'immunisations' => Immunisation::where('school_id', $this->school->id)->with('student:id,first_name,last_name')->orderByDesc('id')->limit(100)->get(),
        ]);
    }
}
