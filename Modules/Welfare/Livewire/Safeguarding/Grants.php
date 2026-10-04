<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Safeguarding;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Support\ImpersonationContext;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\Welfare\Domain\Actions\GrantCaseAccessAction;
use Modules\Welfare\Domain\Actions\RevokeCaseAccessAction;
use Modules\Welfare\Domain\Actions\ViewSafeguardingCaseAction;
use Modules\Welfare\Domain\DataObjects\GrantCaseAccessData;
use Modules\Welfare\Models\CaseAccessGrant;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * `Safeguarding\Grants` (Book G BRD-08 §6 ⭐, access: lead only). Who
 * can see this case, why, until when. `ViewSafeguardingCaseAction`
 * confirms the actor even has access to the case at all (vendor/
 * impersonation hard-excluded there); this screen then additionally
 * confirms the actor IS the lead before allowing any grant/revoke —
 * `GrantCaseAccessAction`/`RevokeCaseAccessAction` themselves don't
 * re-derive that (per their own docblocks, "controller/caller checks
 * permission, Action does the write"), so this check is the actual
 * enforcement point.
 */
#[Title('Case access grants')]
#[Layout('layouts.app')]
final class Grants extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public SafeguardingCase $case;

    public ?int $grantUserId = null;

    public string $accessLevel = 'read';

    public string $reason = '';

    public ?int $expiryDays = null;

    public function mount(School $school, SafeguardingCase $case): void
    {
        $this->loadSchool($school);

        abort_unless($case->school_id === $school->id, 404);

        $user = Auth::user();
        abort_if($user === null, 403);

        try {
            $this->case = app(ViewSafeguardingCaseAction::class)->execute(
                $user,
                $case,
                ImpersonationContext::current(),
                RequestFacade::ip(),
                RequestFacade::userAgent(),
            );
        } catch (InsufficientScopeException) {
            abort(403);
        }

        abort_unless($this->isLead($user->id), 403, __('Grants are managed by the safeguarding lead only.'));
    }

    public function grant(): void
    {
        $this->validate([
            'grantUserId' => ['required', 'integer'],
            'accessLevel' => ['required', 'in:read,contribute,full'],
            'reason' => ['required', 'string'],
        ]);

        app(GrantCaseAccessAction::class)->execute(new GrantCaseAccessData(
            schoolId: $this->school->id,
            caseId: $this->case->id,
            userId: (int) $this->grantUserId,
            accessLevel: $this->accessLevel,
            grantedByUserId: (int) Auth::id(),
            reason: $this->reason,
            expiryDays: $this->expiryDays,
        ));

        $this->reset(['grantUserId', 'reason', 'expiryDays']);
        $this->toast(__('Access granted.'));
    }

    public function revoke(int $grantId): void
    {
        app(RevokeCaseAccessAction::class)->execute($grantId, (int) Auth::id(), __('Revoked from the grants screen.'));

        $this->toast(__('Access revoked.'));
    }

    private function isLead(int $userId): bool
    {
        $scope = new ScopeChain(schoolId: $this->school->id);
        $leadStaffId = app(SettingResolver::class)->get('safeguarding.lead_staff_id', $scope);

        if ($leadStaffId === null || (int) $leadStaffId === 0) {
            return false;
        }

        return Staff::find((int) $leadStaffId)?->user_id === $userId;
    }

    public function render(): View
    {
        return view('welfare::safeguarding.grants', [
            'grants' => CaseAccessGrant::where('case_id', $this->case->id)->orderByDesc('id')->get(),
        ]);
    }
}
