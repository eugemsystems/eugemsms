<?php

declare(strict_types=1);

namespace Modules\Payroll\Livewire\Payslips;

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
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;

/**
 * `Payroll\Payslips\Show` (Book H3 PPL-05 §6, `payroll.view`). Salary
 * and the full calculation trace are absent from the response
 * entirely for a viewer without `people.staff.view_compensation` —
 * the exact `canViewCompensation()` pattern `People\Staff\Show`
 * already established for the same AC-PPL-04-009-shaped rule
 * (AC-PPL-05-010 here), checked via `PermissionScopeResolver`
 * directly rather than an abort, so the rest of the screen still
 * renders for a viewer who merely lacks compensation visibility.
 */
#[Title('Payslip')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Payslip $payslip;

    public function mount(School $school, Payslip $payslip): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('payroll.view');

        abort_unless($payslip->school_id === $school->id, 404);

        $this->payslip = $payslip;
    }

    public function canViewCompensation(): bool
    {
        $user = Auth::user();

        return $user !== null && app(PermissionScopeResolver::class)->has($user, 'people.staff.view_compensation', PermissionScope::Own);
    }

    public function render(): View
    {
        return view('payroll::payslips.show', [
            'lines' => PayslipLine::where('payslip_id', $this->payslip->id)->orderBy('sort_order')->get(),
        ]);
    }
}
