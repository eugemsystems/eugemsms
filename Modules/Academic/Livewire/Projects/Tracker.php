<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ExemptLearnerProjectAction;
use Modules\Academic\Domain\DataObjects\ExemptLearnerProjectData;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectBrief;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Projects\Tracker` (Book E ACA-06 §5/§7, `academic.projects.view`).
 * A brief's learner roster with status at a glance, standing in for the
 * spec's own "brief × learner grid" and doubling as the chase list
 * (anything not yet `submitted`). Exemption (`ExemptLearnerProjectAction`,
 * BR-ACA-06-006) is hosted here rather than on a screen of its own — the
 * same per-row action-bar shape `Selection\Approvals` uses.
 */
#[Title('Project progress tracker')]
#[Layout('layouts.app')]
final class Tracker extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $briefId = null;

    /** @var array<int, string> */
    public array $exemptionReasons = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.projects.view');
    }

    public function exempt(int $learnerProjectId): void
    {
        $this->authorizePermission('academic.projects.manage');

        $reason = trim($this->exemptionReasons[$learnerProjectId] ?? '');

        if ($reason === '') {
            $this->toast(__('An exemption reason is required.'), 'danger');

            return;
        }

        app(ExemptLearnerProjectAction::class)->execute(new ExemptLearnerProjectData(
            learnerProjectId: $learnerProjectId,
            exemptionReason: $reason,
        ));

        unset($this->exemptionReasons[$learnerProjectId]);
        $this->toast(__('Project exempted.'));
    }

    public function render(): View
    {
        return view('academic::projects.tracker', [
            'briefs' => ProjectBrief::where('school_id', $this->school->id)->whereIn('status', ['issued', 'closed'])->orderByDesc('id')->get(),
            'learnerProjects' => $this->briefId !== null
                ? LearnerProject::where('brief_id', $this->briefId)->with('student', 'milestoneSubmissions')->get()
                : collect(),
        ]);
    }
}
