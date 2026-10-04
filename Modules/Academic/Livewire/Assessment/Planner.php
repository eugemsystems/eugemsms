<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Assessment;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateAssessmentAction;
use Modules\Academic\Domain\DataObjects\CreateAssessmentData;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentType;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;

/**
 * `Assessment\Planner` (Book D ACA-05 §6/BR-ACA-05-004,
 * `academic.assessment.manage` to create, `.view` to list). Live
 * weight total per subject this term, with a warning when it does not
 * sum to 100% — `CreateAssessmentAction` itself does not block an
 * intermediate state (an assessment plan is built up one row at a
 * time), so this screen's warning is advisory, exactly matching
 * `ComputeTermSubjectResultsAction`'s own completeness gate at
 * computation time (AC-ACA-05-001).
 */
#[Title('Assessment planner')]
#[Layout('layouts.app')]
final class Planner extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $subjectId = null;

    public ?int $assessmentTypeId = null;

    public string $title = '';

    public string $maxMark = '100';

    public string $weightPercent = '';

    public string $assessedOn = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.assessment.view');

        $this->assessedOn = now()->toDateString();
    }

    public function create(): void
    {
        $this->authorizePermission('academic.assessment.manage');

        $this->validate([
            'subjectId' => ['required', 'integer'],
            'assessmentTypeId' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'maxMark' => ['required', 'numeric', 'min:1'],
            'weightPercent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $termId = SessionContext::termId();
        $yearId = SessionContext::yearId();

        if ($termId === null || $yearId === null) {
            $this->addError('subjectId', __('No active academic year/term is set for this school.'));

            return;
        }

        app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
            schoolId: $this->school->id,
            academicYearId: $yearId,
            termId: $termId,
            assessmentTypeId: (int) $this->assessmentTypeId,
            subjectId: (int) $this->subjectId,
            title: $this->title,
            maxMark: (float) $this->maxMark,
            weightPercent: (float) $this->weightPercent,
            createdByUserId: (int) Auth::id(),
            assessedOn: $this->assessedOn !== '' ? Carbon::parse($this->assessedOn) : null,
        ));

        $this->reset(['title', 'weightPercent']);
        $this->toast(__('Assessment created as draft.'));
    }

    public function render(): View
    {
        $termId = SessionContext::termId();

        $assessments = $termId !== null
            ? Assessment::where('term_id', $termId)->with('subject', 'assessmentType')->orderBy('subject_id')->get()
            : collect();

        $weightTotals = $assessments->groupBy('subject_id')->map(fn ($group) => (float) $group->sum('weight_percent'));

        return view('academic::assessment.planner', [
            'assessments' => $assessments,
            'weightTotals' => $weightTotals,
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
            'assessmentTypes' => AssessmentType::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
