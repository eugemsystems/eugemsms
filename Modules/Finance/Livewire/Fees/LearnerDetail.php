<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Fees;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\People\Models\Student;

/**
 * `Finance\Fees\LearnerDetail` (Book B FIN-02 §7, `finance.fee.view`) —
 * lines, calculation notes, and the "Why this amount?" resolution trace
 * for one learner. Shows every non-superseded assignment for the
 * student, newest first — a learner's whole billing history in one
 * place, not just the current term's.
 */
#[Title('Learner fee detail')]
#[Layout('layouts.app')]
final class LearnerDetail extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Student $student;

    public ?int $expandedAssignmentId = null;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.fee.view');
        $this->student = $student;
    }

    public function toggleTrace(int $assignmentId): void
    {
        $this->expandedAssignmentId = $this->expandedAssignmentId === $assignmentId ? null : $assignmentId;
    }

    public function render(): View
    {
        $assignments = LearnerFeeAssignment::query()
            ->where('student_id', $this->student->id)
            ->where('status', '!=', 'superseded')
            ->with('lines.component', 'structure', 'term')
            ->orderByDesc('computed_at')
            ->get();

        return view('finance::fees.learner-detail', [
            'assignments' => $assignments,
        ]);
    }
}
