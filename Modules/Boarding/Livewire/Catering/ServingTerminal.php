<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Catering;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\GetDietaryAlertsAction;
use Modules\Boarding\Domain\Support\DietaryAlert;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Catering\ServingTerminal` (Book F BRD-04 §6 ⭐, `catering.serve`).
 * Scan a learner, show dietary alerts in large type with their
 * photograph, at every meal — not only the first (BR-BRD-04-009/011).
 * `GetDietaryAlertsAction` runs on every scan; nothing here caches a
 * "already shown once" state.
 */
#[Title('Serving terminal')]
#[Layout('layouts.app')]
final class ServingTerminal extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $search = '';

    public ?Student $scannedStudent = null;

    /** @var Collection<int, DietaryAlert> */
    public Collection $alerts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.catering.serve');
        $this->alerts = collect();
    }

    public function scan(int $studentId): void
    {
        $this->scannedStudent = Student::findOrFail($studentId);
        $this->alerts = app(GetDietaryAlertsAction::class)->execute($studentId);
    }

    public function render(): View
    {
        $results = $this->search !== ''
            ? Student::where('school_id', $this->school->id)
                ->where(fn ($q) => $q->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('admission_number', 'like', "%{$this->search}%"))
                ->limit(10)->get()
            : collect();

        return view('boarding::catering.serving-terminal', [
            'results' => $results,
        ]);
    }
}
