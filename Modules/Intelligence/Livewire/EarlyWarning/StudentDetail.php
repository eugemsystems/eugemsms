<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\EarlyWarning;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Intelligence\Domain\Actions\ComputeLearnerRiskScoreAction;
use Modules\Intelligence\Livewire\Concerns\ResolvesEarlyWarningTerm;
use Modules\Intelligence\Models\LearnerRiskScore;
use Modules\People\Models\Student;

/**
 * `Intelligence\EarlyWarning\StudentDetail` (Book J INT-03 §5,
 * `risk.review`). The decomposed score: every factor with its plain
 * language, weight, contribution and source (BR-INT-03-001), plus the
 * score history across terms (BR-INT-03-005). Staff-only — the page
 * says so, and no learner or guardian surface calls this.
 */
#[Title('Learner risk detail')]
#[Layout('layouts.app')]
final class StudentDetail extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesEarlyWarningTerm;
    use Toasts;

    public int $studentId;

    public function mount(School $school, int $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('risk.review');

        $this->studentId = Student::where('school_id', $school->id)->findOrFail($student)->id;
        $this->termId = $this->defaultTermId();
    }

    public function recompute(): void
    {
        $this->authorizePermission('risk.review');

        $term = $this->selectedTerm();
        $student = Student::where('school_id', $this->school->id)->findOrFail($this->studentId);

        if ($term === null) {
            $this->toast(__('Choose a term first.'), 'danger');

            return;
        }

        app(ComputeLearnerRiskScoreAction::class)->execute($this->school->id, $student->id, $term->id);

        $this->toast(__('Recomputed from source data.'));
    }

    public function render(): View
    {
        $student = Student::where('school_id', $this->school->id)->findOrFail($this->studentId);
        $term = $this->selectedTerm();
        $history = LearnerRiskScore::where('school_id', $this->school->id)->where('student_id', $student->id)->orderByDesc('computed_at')->get();

        return view('intelligence::early-warning.student-detail', [
            'student' => $student,
            'terms' => $this->termChoices(),
            'score' => $term === null ? null : $history->firstWhere('term_id', $term->id),
            'history' => $history,
            'termNames' => Term::where('school_id', $this->school->id)->whereIn('id', $history->pluck('term_id'))->pluck('name', 'id'),
        ]);
    }
}
