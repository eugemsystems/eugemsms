<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Guardians;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\Guardian;
use Modules\People\Models\StudentGuardian;

/**
 * `People\Guardians\Show` (Book C PPL-03 §8, `people.guardians.view`).
 * Linked learners and active fee-liability rules — both read-only
 * here; managing either lives on the student-scoped screens
 * (`People\Students\Guardians` for the relationship, Finance's own
 * `Finance\Liabilities\Editor` for the liability rules, per
 * `DeactivateFeeLiabilityAction`'s own docblock).
 */
#[Title('Guardian profile')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Guardian $guardian;

    public function mount(School $school, Guardian $guardian): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.guardians.view');

        abort_unless($guardian->school_id === $school->id, 404);

        $this->guardian = $guardian;
    }

    public function render(): View
    {
        return view('people::guardians.show', [
            'studentLinks' => StudentGuardian::where('guardian_id', $this->guardian->id)->with('student')->get(),
            'liabilities' => $this->activeLiabilities(),
        ]);
    }

    /**
     * @return Collection<int, FeeLiability>
     */
    private function activeLiabilities(): Collection
    {
        return FeeLiability::where('guardian_id', $this->guardian->id)
            ->where('is_active', true)
            ->with('student', 'component')
            ->orderBy('priority')
            ->get();
    }
}
