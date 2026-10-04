<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Leave;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CancelLeaveRequestAction;
use Modules\People\Domain\Actions\RequestLeaveAction;
use Modules\People\Domain\DataObjects\CancelLeaveRequestData;
use Modules\People\Domain\DataObjects\RequestLeaveData;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\LeaveType;
use Modules\People\Models\Staff;

/**
 * `People\Leave\Request` (Book C PPL-04 §5, `people.staff.leave_view`).
 * This admin panel is internal-staff-facing throughout (see CLAUDE.md's
 * tech-stack table), not an employee self-service portal, so "own"
 * scope from the spec's own permission table is simplified here to an
 * HR/registrar user capturing a request on a staff member's behalf —
 * there is no separate staff self-service login path in this pass.
 */
#[Title('Request leave')]
#[Layout('layouts.app')]
final class Request extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $staffId = null;

    public ?int $leaveTypeId = null;

    public string $startsOn = '';

    public string $endsOn = '';

    public string $workingDays = '';

    public string $reason = '';

    public ?int $coverStaffId = null;

    public bool $approveOverdraft = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('people.staff.leave_view');

        $this->startsOn = now()->toDateString();
        $this->endsOn = now()->toDateString();
    }

    public function submit(): void
    {
        $this->validate([
            'staffId' => ['required', 'integer'],
            'leaveTypeId' => ['required', 'integer'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
            'workingDays' => ['required', 'numeric', 'min:0.5'],
        ]);

        $yearId = SessionContext::yearId();

        if ($yearId === null) {
            $this->addError('staffId', __('No active academic year is set for this school.'));

            return;
        }

        try {
            app(RequestLeaveAction::class)->execute(new RequestLeaveData(
                schoolId: $this->school->id,
                staffId: (int) $this->staffId,
                leaveTypeId: (int) $this->leaveTypeId,
                academicYearId: $yearId,
                startsOn: Carbon::parse($this->startsOn),
                endsOn: Carbon::parse($this->endsOn),
                workingDays: $this->workingDays,
                reason: $this->reason !== '' ? $this->reason : null,
                coverStaffId: $this->coverStaffId,
                approveOverdraft: $this->approveOverdraft,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['leaveTypeId', 'workingDays', 'reason', 'coverStaffId', 'approveOverdraft']);
        $this->toast(__('Leave request submitted.'));
    }

    public function cancel(int $leaveRequestId): void
    {
        try {
            app(CancelLeaveRequestAction::class)->execute(new CancelLeaveRequestData(
                leaveRequestId: $leaveRequestId,
                cancelledByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Leave request cancelled.'));
    }

    public function render(): View
    {
        return view('people::leave.request', [
            'staffList' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
            'leaveTypes' => LeaveType::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
            'requests' => LeaveRequest::where('school_id', $this->school->id)->with('staff', 'leaveType')->orderByDesc('id')->limit(30)->get(),
        ]);
    }
}
