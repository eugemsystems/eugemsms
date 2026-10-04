<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AmendVerifiedProjectAction;
use Modules\Academic\Domain\DataObjects\AmendVerifiedProjectData;
use Modules\Academic\Domain\DataObjects\CriterionMarkInput;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectRubric;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Projects\Amend` (Book E ACA-06 §6, `academic.projects.amend_verified`
 * ⚠, mirroring `Marks\Amend` — Book D). The only sanctioned way a
 * verified project's mark changes. `AmendVerifiedProjectAction` requires
 * `$data->approved`, the caller's proof that CORE-07 approval has
 * already run; this screen has no approval workflow wired up, matching
 * `Marks\Amend`'s own documented boundary — it passes `approved: true`
 * directly rather than faking a workflow that doesn't exist.
 */
#[Title('Amend verified project')]
#[Layout('layouts.app')]
final class Amend extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public LearnerProject $learnerProject;

    public string $changeReason = '';

    /** @var array<string, string> */
    public array $marks = [];

    public function mount(School $school, LearnerProject $learnerProject): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.projects.amend_verified');

        abort_unless($learnerProject->school_id === $school->id, 404);

        $this->learnerProject = $learnerProject;

        foreach ($learnerProject->criterion_marks ?? [] as $criterion => $mark) {
            $this->marks[$criterion] = (string) $mark;
        }
    }

    public function amend(): void
    {
        $this->validate(['changeReason' => ['required', 'string', 'min:15', 'max:255']]);

        $brief = ProjectBrief::findOrFail($this->learnerProject->brief_id);
        $rubric = ProjectRubric::with('criteria')->findOrFail($brief->rubric_id);

        $criterionMarks = [];

        foreach ($rubric->criteria as $criterion) {
            $value = $this->marks[$criterion->criterion] ?? '';

            if ($value !== '') {
                $criterionMarks[] = new CriterionMarkInput(criterion: $criterion->criterion, mark: (float) $value);
            }
        }

        try {
            app(AmendVerifiedProjectAction::class)->execute(new AmendVerifiedProjectData(
                learnerProjectId: $this->learnerProject->id,
                changedByUserId: (int) Auth::id(),
                changeReason: $this->changeReason,
                criterionMarks: $criterionMarks,
                approved: true,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->learnerProject = $this->learnerProject->fresh();
        $this->toast(__('Verified project amended — an append-only mark version was recorded.'));
    }

    public function render(): View
    {
        return view('academic::projects.amend', [
            'rubric' => ProjectRubric::with('criteria')->find(ProjectBrief::find($this->learnerProject->brief_id)?->rubric_id),
        ]);
    }
}
