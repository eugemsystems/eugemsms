<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\VerifyProjectAction;
use Modules\Academic\Domain\DataObjects\VerifyProjectData;
use Modules\Academic\Models\LearnerProject;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Projects\Verify` (Book E ACA-06 §5/§6/§7/BR-ACA-06-014,
 * `academic.projects.verify`, HOD). Only a verified outcome counts
 * toward the final mark (`ContinuousAssessmentProvider::outcomeFor()`)
 * — a project not sampled for moderation may verify straight from
 * `marked`.
 */
#[Title('Verify projects')]
#[Layout('layouts.app')]
final class Verify extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.projects.verify');
    }

    public function verify(int $learnerProjectId): void
    {
        try {
            app(VerifyProjectAction::class)->execute(new VerifyProjectData(
                learnerProjectId: $learnerProjectId,
                verifiedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Project verified — it now counts toward the final subject mark.'));
    }

    public function render(): View
    {
        return view('academic::projects.verify', [
            'projects' => LearnerProject::where('school_id', $this->school->id)
                ->whereIn('status', ['marked', 'moderated'])
                ->with('student', 'brief')
                ->get(),
        ]);
    }
}
