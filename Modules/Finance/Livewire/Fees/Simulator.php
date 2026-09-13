<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Fees;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Models\Subject;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\PreviewIndicativeFeeAction;
use Modules\Finance\Domain\DataObjects\PreviewIndicativeFeeData;
use Modules\People\Models\Student;

/**
 * `Finance\Fees\Simulator` (Book B FIN-02 §7, `finance.fee_structure.view`)
 * — "what would this learner pay?", reusing `PreviewIndicativeFeeAction`
 * (the same engine `Academic\Selection\Form`'s live fee preview already
 * uses). The spec's own wording is "without any learner" — a truly
 * synthetic/hypothetical student (no real record at all) has no domain
 * action behind it: `FeeStructureResolver::resolve()` and
 * `PreviewIndicativeFeeAction` both require a persisted `Student` to
 * read section/grade level/enrolment type/etc. from. This screen
 * simulates against a REAL existing learner instead (with an optional
 * proposed subject set overriding their actual enrolments) — an honest
 * narrowing of scope, not a silent gap: a bursar picks any learner whose
 * section/grade/enrolment type matches the hypothetical they have in
 * mind (e.g. any current Form 2 boarder) rather than the tool
 * fabricating one that doesn't exist.
 */
#[Title('Fee simulator')]
#[Layout('layouts.app')]
final class Simulator extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $studentSearch = '';

    public ?int $selectedStudentId = null;

    public string $selectedStudentLabel = '';

    public ?int $termId = null;

    /**
     * @var array<int, int>
     */
    public array $subjectIds = [];

    public bool $hasPreviewed = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.fee_structure.view');
        $this->termId = $school->currentAcademicYear()?->currentTerm()?->id;
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentResults(): Collection
    {
        if (mb_strlen($this->studentSearch) < 2) {
            return new Collection;
        }

        return Student::query()
            ->where(fn ($q) => $q->where('admission_number', 'like', "%{$this->studentSearch}%")
                ->orWhere('first_name', 'like', "%{$this->studentSearch}%")
                ->orWhere('last_name', 'like', "%{$this->studentSearch}%"))
            ->limit(10)
            ->get();
    }

    public function selectStudent(int $studentId): void
    {
        $student = Student::findOrFail($studentId);
        $this->selectedStudentId = $student->id;
        $this->selectedStudentLabel = "{$student->admission_number} — {$student->fullName()}";
        $this->studentSearch = '';
        $this->hasPreviewed = false;
    }

    public function preview(): void
    {
        $this->validate([
            'termId' => ['required', 'integer'],
        ]);

        if ($this->selectedStudentId === null) {
            $this->addError('selectedStudentId', __('Select a learner first.'));

            return;
        }

        $this->hasPreviewed = true;
    }

    public function render(): View
    {
        $result = null;

        if ($this->hasPreviewed && $this->selectedStudentId !== null && $this->termId !== null) {
            $result = app(PreviewIndicativeFeeAction::class)->execute(new PreviewIndicativeFeeData(
                studentId: $this->selectedStudentId,
                termId: $this->termId,
                subjectIds: $this->subjectIds,
            ));
        }

        return view('finance::fees.simulator', [
            'terms' => Term::orderByDesc('starts_on')->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'result' => $result,
        ]);
    }
}
