<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Leave;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreateLeaveTypeAction;
use Modules\People\Domain\DataObjects\CreateLeaveTypeData;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveType;

/**
 * `People\Leave\Balances` (Book C PPL-04 §5, `people.staff.leave_view`
 * to read; creating a leave type needs `people.staff.leave_approve`,
 * the closer HR-admin permission — the spec names no distinct
 * leave-type permission of its own). Folds the spec's leave-type
 * management into this screen rather than a standalone one, since it
 * is otherwise a single small create-only form.
 */
#[Title('Leave balances')]
#[Layout('layouts.app')]
final class Balances extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $accrualMethod = 'annual';

    public string $annualEntitlementDays = '';

    public bool $isPaid = true;

    public bool $requiresDocument = false;

    public bool $requiresCover = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.leave_view');
    }

    public function createLeaveType(): void
    {
        $this->authorizePermission('people.staff.leave_approve');

        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:80'],
            'accrualMethod' => ['required', 'in:annual,monthly,none'],
            'annualEntitlementDays' => ['nullable', 'numeric', 'min:0'],
        ]);

        app(CreateLeaveTypeAction::class)->execute(new CreateLeaveTypeData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            accrualMethod: $this->accrualMethod,
            annualEntitlementDays: $this->annualEntitlementDays !== '' ? $this->annualEntitlementDays : null,
            isPaid: $this->isPaid,
            requiresDocument: $this->requiresDocument,
            requiresCover: $this->requiresCover,
        ));

        $this->reset(['code', 'name', 'annualEntitlementDays', 'requiresDocument']);
        $this->toast(__('Leave type created.'));
    }

    public function render(): View
    {
        return view('people::leave.balances', [
            'leaveTypes' => LeaveType::where('school_id', $this->school->id)->orderBy('name')->get(),
            'balances' => LeaveBalance::where('school_id', $this->school->id)->with('staff', 'leaveType')->orderBy('staff_id')->get(),
        ]);
    }
}
