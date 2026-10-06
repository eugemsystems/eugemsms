<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Results;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CheckAssessmentWeightsAction;
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
 * chosen class. A subject whose assessment weights do not total 100%
 * blocks the run and is named with its total (BR-ACA-05-004,
 * AC-ACA-05-001); `ComputeTermSubjectResultsAction` enforces the same rule.
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

        $problems = app(CheckAssessmentWeightsAction::class)->execute($termId);

        if ($problems !== []) {
            $this->weightExceptions = array_map(fn (array $p): string => sprintf('%s: %s%%', $p['subject'], number_format($p['total_percent'], 1)), $problems);
            $this->toast(__('Results cannot be computed until every subject\'s assessment weights total 100%.'), 'danger');

            return;
        }

        $this->weightExceptions = [];

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

        $this->toast(__('Results computed for :count learner(s).', ['count' => $studentIds->count()]));
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
