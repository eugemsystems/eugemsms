<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Staff;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\DutyAssignment;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffAppraisal;
use Modules\People\Models\StaffDocument;
use Modules\People\Models\StaffWorkload;
use Modules\People\Models\TeacherAllocation;

/**
 * `People\Staff\Show` (Book C PPL-04 §5 ⭐, `people.staff.view`). Salary/
 * banking fields on the Employment tab are absent from the response
 * entirely for a viewer without `people.staff.view_compensation` —
 * checked via `PermissionScopeResolver` directly (no abort), per
 * BR-PPL-04-019/AC-PPL-04-009 and this codebase's project-wide "a
 * sensitive field is absent, never merely hidden client-side" rule.
 * Disciplinary cases are linked out to their own screen rather than
 * shown inline — `ViewDisciplinaryCaseAction` is the only sanctioned
 * read path and this tab would otherwise bypass it.
 */
#[Title('Staff profile')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Staff $staff;

    public string $activeTab = 'personal';

    public function mount(School $school, Staff $staff): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.view');

        abort_unless($staff->school_id === $school->id, 404);

        $this->staff = $staff;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function canViewCompensation(): bool
    {
        $user = Auth::user();

        return $user !== null && app(PermissionScopeResolver::class)->has($user, 'people.staff.view_compensation', PermissionScope::Own);
    }

    public function render(): View
    {
        return view('people::staff.show', [
            'allocations' => TeacherAllocation::where('staff_id', $this->staff->id)->where('status', 'active')->with('subject', 'schoolClass')->get(),
            'workloads' => StaffWorkload::where('staff_id', $this->staff->id)->orderByDesc('term_id')->get(),
            'leaveBalances' => LeaveBalance::where('staff_id', $this->staff->id)->with('leaveType')->get(),
            'leaveRequests' => LeaveRequest::where('staff_id', $this->staff->id)->with('leaveType')->orderByDesc('starts_on')->limit(10)->get(),
            'dutyAssignments' => DutyAssignment::where('staff_id', $this->staff->id)->with('roster')->orderByDesc('starts_at')->limit(10)->get(),
            'appraisals' => StaffAppraisal::where('staff_id', $this->staff->id)->orderByDesc('id')->get(),
            'documents' => StaffDocument::where('staff_id', $this->staff->id)->get(),
        ]);
    }
}
