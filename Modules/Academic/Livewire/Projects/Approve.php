<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ApproveProjectBriefAction;
use Modules\Academic\Domain\Actions\IssueProjectBriefAction;
use Modules\Academic\Domain\DataObjects\ApproveProjectBriefData;
use Modules\Academic\Domain\DataObjects\IssueProjectBriefData;
use Modules\Academic\Models\ProjectBrief;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Projects\Approve` (Book E ACA-06 §5/BR-ACA-06-004/008,
 * `academic.projects.approve`, HOD). One lifecycle screen hosting both
 * the approve and the issue action bar per brief row — the same "one
 * screen, whole lifecycle" shape as `Selection\Approvals` (Book D),
 * since neither transition has enough surface of its own to earn a
 * separate route.
 */
#[Title('Brief approval')]
#[Layout('layouts.app')]
final class Approve extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.projects.approve');
    }

    public function approve(int $briefId): void
    {
        $staffId = $this->actingStaffId();

        if ($staffId === null) {
            $this->toast(__('Your account has no staff record linked at this school.'), 'danger');

            return;
        }

        try {
            app(ApproveProjectBriefAction::class)->execute(new ApproveProjectBriefData(
                briefId: $briefId,
                approvedByStaffId: $staffId,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Brief approved — ready to issue.'));
    }

    /**
     * `ApproveProjectBriefData::$approvedByStaffId` is a `staff.id`, not
     * `Auth::id()`'s `users.id` — resolved here from the acting user's
     * own linked staff record rather than conflating the two id spaces.
     */
    private function actingStaffId(): ?int
    {
        return Staff::where('school_id', $this->school->id)->where('user_id', Auth::id())->value('id');
    }

    public function issue(int $briefId): void
    {
        try {
            app(IssueProjectBriefAction::class)->execute(new IssueProjectBriefData(briefId: $briefId));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Brief issued — a project was created for every currently-enrolled learner.'));
    }

    public function render(): View
    {
        return view('academic::projects.approve', [
            'briefs' => ProjectBrief::where('school_id', $this->school->id)
                ->whereIn('status', ['draft', 'approved'])
                ->with('subject', 'gradeLevel')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
