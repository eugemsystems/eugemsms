<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\MarkProjectAction;
use Modules\Academic\Domain\DataObjects\CriterionMarkInput;
use Modules\Academic\Domain\DataObjects\MarkProjectData;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectRubric;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Projects\Mark` (Book E ACA-06 §5/§6/§7 ⭐, `academic.projects.mark`).
 * A marking queue (submitted projects for a chosen brief) plus a
 * criterion-by-criterion entry form with a running total — the spec's
 * own "evidence viewer beside the rubric" is not built (no evidence
 * list surfaced here; `ProjectEvidence` rows exist but this pass's
 * screens are staff-marking-focused, not a document viewer).
 */
#[Title('Mark projects')]
#[Layout('layouts.app')]
final class Mark extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $briefId = null;

    public ?int $selectedProjectId = null;

    /** @var array<string, string> */
    public array $marks = [];

    public string $markerComment = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.projects.mark');
    }

    public function select(int $learnerProjectId): void
    {
        $this->selectedProjectId = $learnerProjectId;
        $this->marks = [];
        $this->markerComment = '';
    }

    public function save(): void
    {
        if ($this->selectedProjectId === null) {
            return;
        }

        $brief = ProjectBrief::find($this->briefId);
        $rubric = $brief !== null ? ProjectRubric::with('criteria')->find($brief->rubric_id) : null;

        if ($rubric === null) {
            $this->toast(__('No rubric found for this brief.'), 'danger');

            return;
        }

        $staffId = Staff::where('school_id', $this->school->id)->where('user_id', Auth::id())->value('id');

        if ($staffId === null) {
            $this->toast(__('Your account has no staff record linked at this school.'), 'danger');

            return;
        }

        $criterionMarks = [];

        foreach ($rubric->criteria as $criterion) {
            $value = $this->marks[$criterion->criterion] ?? '';

            if ($value === '') {
                continue;
            }

            $criterionMarks[] = new CriterionMarkInput(criterion: $criterion->criterion, mark: (float) $value);
        }

        try {
            app(MarkProjectAction::class)->execute(new MarkProjectData(
                learnerProjectId: $this->selectedProjectId,
                markerStaffId: $staffId,
                criterionMarks: $criterionMarks,
                markerComment: $this->markerComment !== '' ? $this->markerComment : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->selectedProjectId = null;
        $this->marks = [];
        $this->toast(__('Project marked.'));
    }

    public function render(): View
    {
        $brief = $this->briefId !== null ? ProjectBrief::find($this->briefId) : null;
        $rubric = $brief !== null ? ProjectRubric::with('criteria')->find($brief->rubric_id) : null;

        return view('academic::projects.mark', [
            'briefs' => ProjectBrief::where('school_id', $this->school->id)->where('status', 'issued')->orderByDesc('id')->get(),
            'queue' => $this->briefId !== null
                ? LearnerProject::where('brief_id', $this->briefId)->where('status', 'submitted')->with('student')->get()
                : collect(),
            'rubric' => $rubric,
        ]);
    }
}
