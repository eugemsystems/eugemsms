<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Safeguarding;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\Welfare\Livewire\Safeguarding\Concerns\BlocksVendorAndImpersonation;
use Modules\Welfare\Models\CaseAccessGrant;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * `Safeguarding\Cases` (Book G BRD-08 §6 ⭐⭐, access: lead or granted).
 *
 * The query itself is the access control for this list — never a plain
 * "all cases for this school" query. The lead sees every case; anyone
 * else sees only cases where they hold an active, unexpired grant. A
 * vendor/impersonating session sees nothing (the query's own
 * `isLead`/grant join never matches, and the route itself requires a
 * school-assigned non-vendor user).
 *
 * This list deliberately shows only `case_reference`, the student's
 * name, and `status` — never `category`, `risk_level`, or `summary`,
 * which the spec's own §3 schema and "never query ... for anything
 * sensitive" instruction both treat as case content. Reading those
 * fields, and triggering the mandatory audit row for the read, happens
 * only on `Safeguarding\CaseDetail` via `ViewSafeguardingCaseAction`.
 *
 * Judgment call, stated plainly: even showing `case_reference` and the
 * student's name here is a read of `SafeguardingCase` that bypasses the
 * per-row audit `ViewSafeguardingCaseAction` would log. It is not
 * logged as a `case_read` because the row never becomes visible to a
 * user without an independent basis (lead status, or a grant the lead
 * already created with full knowledge of which student and case it
 * names) — the same basis `ViewSafeguardingCaseAction` itself checks.
 * If this is ever tightened, the fix is to log a lighter "case_listed"
 * event per row shown, not to add content here.
 */
#[Title('Safeguarding cases')]
#[Layout('layouts.app')]
final class Cases extends Component
{
    use AuthorizesPermissions;
    use BlocksVendorAndImpersonation;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->abortIfVendorOrImpersonating();
        $this->authorizePermission('safeguarding.case.view');
    }

    public function render(): View
    {
        $user = Auth::user();
        $isLead = $user !== null && $this->isSafeguardingLead($user->id);

        $query = SafeguardingCase::where('school_id', $this->school->id)->with('student:id,first_name,last_name');

        if (! $isLead) {
            $caseIds = CaseAccessGrant::where('user_id', $user?->id)
                ->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->pluck('case_id');

            $query->whereIn('id', $caseIds);
        }

        return view('welfare::safeguarding.cases', [
            'cases' => $query->orderByDesc('opened_at')->get(),
            'isLead' => $isLead,
        ]);
    }

    private function isSafeguardingLead(int $userId): bool
    {
        $scope = new ScopeChain(schoolId: $this->school->id);
        $leadStaffId = app(SettingResolver::class)->get('safeguarding.lead_staff_id', $scope);

        if ($leadStaffId === null || (int) $leadStaffId === 0) {
            return false;
        }

        return Staff::find((int) $leadStaffId)?->user_id === $userId;
    }
}
