<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Student;
use Modules\People\Models\StudentAttributeChange;
use Modules\People\Models\StudentEnrolment;
use Modules\People\Models\StudentGuardian;

/**
 * `People\Students\Show` (Book C PPL-01 §8 ⭐, `students.view`) — "the
 * single most-used screen in the product." The spec's own tab list
 * (overview, academic, financial, guardians, boarding, health flags,
 * discipline, documents, timeline) assumes modules this pass doesn't
 * have UI for yet (`BRD-*` boarding/health/discipline) or whose own
 * backing table was never built at all (`student_documents`,
 * `student_timeline` — see `.ai/rules/people.md`). This screen keeps
 * the tabs that have something real behind them today: Overview,
 * Academic (enrolment + billing-attribute history), Financial
 * (`Invoice.balance_minor`, grouped by currency — never a `student`
 * subledger balance, same trap `.ai/rules/finance.md` already
 * documents), and Guardians (read-only; `PPL-03` owns managing these,
 * no admin UI for that yet either).
 */
#[Title('Student profile')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Student $student;

    public string $activeTab = 'overview';

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.students.view');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render(): View
    {
        return view('people::students.show', [
            'guardianLinks' => StudentGuardian::where('student_id', $this->student->id)->with('guardian')->get(),
            'balancesByCurrency' => $this->balancesByCurrency(),
            'enrolmentHistory' => StudentEnrolment::where('student_id', $this->student->id)->orderByDesc('started_on')->get(),
            'attributeHistory' => StudentAttributeChange::where('student_id', $this->student->id)->orderByDesc('effective_from')->get(),
        ]);
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function balancesByCurrency(): Collection
    {
        return Invoice::where('student_id', $this->student->id)
            ->where('balance_minor', '>', 0)
            ->selectRaw('currency, sum(balance_minor) as total_minor')
            ->groupBy('currency')
            ->get();
    }
}
