<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Results;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ComputeTermResultsAction;
use Modules\Academic\Domain\Actions\ComputeTermSubjectResultsAction;
use Modules\Academic\Domain\Actions\RecomputeSubjectPositionsAction;
use Modules\Academic\Domain\Actions\RecomputeTermPositionsAction;
use Modules\Academic\Domain\DataObjects\ComputeTermResultsData;
use Modules\Academic\Domain\DataObjects\ComputeTermSubjectResultsData;
use Modules\Academic\Domain\DataObjects\RecomputeSubjectPositionsData;
use Modules\Academic\Domain\DataObjects\RecomputeTermPositionsData;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;

/**
 * `Results\Compute` (Book D ACA-05 §3/§6, `academic.result.compute`).
 * Runs the full `ComputeTermSubjectResultsAction` ->
 * `RecomputeSubjectPositionsAction` -> `ComputeTermResultsAction` ->
 * `RecomputeTermPositionsAction` pipeline for every learner in a
 * chosen class. The exception report (weight totals ≠ 100%) is
 * advisory here, not a block: `ComputeTermSubjectResultsAction` itself
 * does not refuse computation on a shortfall the way AC-ACA-05-001
 * describes — a genuine, documented gap (see `.ai/rules/academic.md`)
 * — so this screen computes the shortfall list itself and surfaces it
 * alongside the results rather than silently proceeding.
 */
#[Title('Compute results')]
#[Layout('layouts.app')]
final class Compute extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $classId = null;

    /** @var array<int, string> */
    public array $weightExceptions = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.result.compute');
    }

    public function compute(): void
    {
        $this->validate(['classId' => ['required', 'integer']]);

        $termId = SessionContext::termId();
        $yearId = SessionContext::yearId();

        if ($termId === null || $yearId === null) {
            $this->toast(__('No active academic year/term is set for this school.'), 'danger');

            return;
        }

        $studentIds = ClassAllocation::where('class_id', $this->classId)->where('term_id', $termId)->where('status', 'confirmed')->pluck('student_id');
        $touchedSubjectIds = [];

        foreach ($studentIds as $studentId) {
            $subjectIds = LearnerSubjectEnrolment::where('student_id', $studentId)->where('term_id', $termId)->where('status', 'active')->pluck('subject_id');

            foreach ($subjectIds as $subjectId) {
                app(ComputeTermSubjectResultsAction::class)->execute(new ComputeTermSubjectResultsData(
                    studentId: $studentId,
                    subjectId: $subjectId,
                    academicYearId: $yearId,
                    termId: $termId,
                ));

                $touchedSubjectIds[$subjectId] = true;
            }
        }

        foreach (array_keys($touchedSubjectIds) as $subjectId) {
            app(RecomputeSubjectPositionsAction::class)->execute(new RecomputeSubjectPositionsData(
                schoolId: $this->school->id,
                termId: $termId,
                subjectId: $subjectId,
            ));
        }

        foreach ($studentIds as $studentId) {
            app(ComputeTermResultsAction::class)->execute(new ComputeTermResultsData(studentId: $studentId, termId: $termId));
        }

        app(RecomputeTermPositionsAction::class)->execute(new RecomputeTermPositionsData(
            schoolId: $this->school->id,
            termId: $termId,
            classId: (int) $this->classId,
        ));

        $this->computeWeightExceptions($termId);

        $this->toast(__('Results computed for :count learner(s).', ['count' => $studentIds->count()]));
    }

    private function computeWeightExceptions(int $termId): void
    {
        $this->weightExceptions = [];

        $totals = Assessment::where('term_id', $termId)
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($group) => (float) $group->sum('weight_percent'));

        foreach ($totals as $subjectId => $total) {
            if (abs($total - 100.0) > 0.01) {
                $subject = Subject::find($subjectId);
                $label = $subject !== null ? $subject->name : "#{$subjectId}";
                $this->weightExceptions[] = sprintf('%s: %s%%', $label, number_format($total, 1));
            }
        }
    }

    public function render(): View
    {
        $termId = SessionContext::termId();

        return view('academic::results.compute', [
            'classes' => SchoolClass::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
            'results' => $this->classId !== null && $termId !== null
                ? TermResult::where('term_id', $termId)->where('class_id', $this->classId)->with('student')->orderBy('class_position')->get()
                : collect(),
        ]);
    }
}
