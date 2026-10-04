<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Behaviour;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\RebuildBehaviourPointBalanceAction;
use Modules\Welfare\Models\BehaviourPointBalance;
use Modules\Welfare\Models\BehaviourRecord;

/**
 * `Behaviour\Learner` (Book G BRD-07 §5, `behaviour.view`). Timeline,
 * points, both polarities. Folds in the spec's separate "Conduct
 * grades" screen — the computed grade is simply this student's own
 * current-term `BehaviourPointBalance.conduct_grade`, so a separate
 * screen would only duplicate this one's own rebuild button.
 * `is_confidential` records show only that a matter is under review,
 * per BR-BRD-07-018's own "general staff lose visibility of detail".
 */
#[Title('Learner behaviour')]
#[Layout('layouts.app')]
final class Learner extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Student $student;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.view');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
    }

    public function rebuildBalance(): void
    {
        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        app(RebuildBehaviourPointBalanceAction::class)->execute($this->school->id, $this->student->id, $term->id);

        $this->toast(__('Points and conduct grade recomputed.'));
    }

    public function render(): View
    {
        return view('welfare::behaviour.learner', [
            'records' => BehaviourRecord::where('student_id', $this->student->id)
                ->with('category:id,name,polarity')
                ->orderByDesc('occurred_at')
                ->limit(100)
                ->get(),
            'balances' => BehaviourPointBalance::where('student_id', $this->student->id)->orderByDesc('term_id')->get(),
        ]);
    }
}
