<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Selection;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\GuardianApproveSubjectSelectionAction;
use Modules\Academic\Domain\Actions\RejectSubjectSelectionAction;
use Modules\Academic\Domain\Actions\SchoolApproveSubjectSelectionAction;
use Modules\Academic\Domain\DataObjects\GuardianApproveSubjectSelectionData;
use Modules\Academic\Domain\DataObjects\RejectSubjectSelectionData;
use Modules\Academic\Domain\DataObjects\SchoolApproveSubjectSelectionData;
use Modules\Academic\Models\SubjectSelectionSubmission;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Selection\Approvals` (Book D ACA-02 §6/BR-ACA-02-015,
 * `academic.selection.approve`). One lifecycle screen hosting the
 * whole guardian-approve / school-approve / reject action bar per
 * submission row, rather than a screen per transition — the same
 * shape as `Admissions\Applications\Show`'s own action bar, just
 * inlined into a list since no submission here has enough surface to
 * earn its own detail route.
 */
#[Title('Subject selection approvals')]
#[Layout('layouts.app')]
final class Approvals extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, string> */
    public array $rejectionReasons = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.selection.approve');
    }

    public function guardianApprove(int $submissionId): void
    {
        try {
            app(GuardianApproveSubjectSelectionAction::class)->execute(new GuardianApproveSubjectSelectionData(
                submissionId: $submissionId,
                approvedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Guardian approval recorded.'));
    }

    public function schoolApprove(int $submissionId): void
    {
        try {
            app(SchoolApproveSubjectSelectionAction::class)->execute(new SchoolApproveSubjectSelectionData(
                submissionId: $submissionId,
                approvedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Selection approved and allocated.'));
    }

    public function reject(int $submissionId): void
    {
        $reason = trim($this->rejectionReasons[$submissionId] ?? '');

        if ($reason === '') {
            $this->toast(__('A rejection reason is required.'), 'danger');

            return;
        }

        try {
            app(RejectSubjectSelectionAction::class)->execute(new RejectSubjectSelectionData(
                submissionId: $submissionId,
                rejectionReason: $reason,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        unset($this->rejectionReasons[$submissionId]);
        $this->toast(__('Selection rejected.'));
    }

    public function render(): View
    {
        return view('academic::selection.approvals', [
            'submissions' => SubjectSelectionSubmission::where('school_id', $this->school->id)
                ->whereIn('status', ['submitted', 'guardian_approved', 'school_approved'])
                ->with('student', 'gradeLevel', 'pathway')
                ->orderByDesc('submitted_at')
                ->get(),
        ]);
    }
}
