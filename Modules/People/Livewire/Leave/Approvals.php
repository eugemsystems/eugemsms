<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Leave;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\ApproveLeaveRequestAction;
use Modules\People\Domain\Actions\RejectLeaveRequestAction;
use Modules\People\Domain\DataObjects\ApproveLeaveRequestData;
use Modules\People\Domain\DataObjects\RejectLeaveRequestData;
use Modules\People\Models\LeaveRequest;

/**
 * `People\Leave\Approvals` (Book C PPL-04 §5/BR-PPL-04-011,
 * `people.staff.leave_approve`).
 */
#[Title('Leave approvals')]
#[Layout('layouts.app')]
final class Approvals extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $rejectReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.leave_approve');
    }

    public function approve(int $leaveRequestId): void
    {
        try {
            app(ApproveLeaveRequestAction::class)->execute(new ApproveLeaveRequestData(
                leaveRequestId: $leaveRequestId,
                approvedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Leave approved.'));
    }

    public function reject(int $leaveRequestId): void
    {
        try {
            app(RejectLeaveRequestAction::class)->execute(new RejectLeaveRequestData(
                leaveRequestId: $leaveRequestId,
                rejectedByUserId: (int) Auth::id(),
                reason: $this->rejectReason !== '' ? $this->rejectReason : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->rejectReason = '';
        $this->toast(__('Leave rejected.'));
    }

    public function render(): View
    {
        return view('people::leave.approvals', [
            'pending' => LeaveRequest::where('school_id', $this->school->id)->where('status', 'pending')->with('staff', 'leaveType', 'coverStaff')->orderBy('starts_on')->get(),
        ]);
    }
}
