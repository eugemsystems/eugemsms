<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Users;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\GrantSupportAccessAction;
use Modules\Core\Domain\Actions\Auth\RevokeSupportAccessAction;
use Modules\Core\Domain\DataObjects\Auth\GrantSupportAccessData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\School;
use Modules\Core\Models\SupportAccessGrant;

/**
 * `Core\Users\SupportAccess` (Book J SAA-02 BR-SAA-02-002, `core.support_access.manage`). Where a
 * customer's own administrator consents to — and can at any moment withdraw — vendor support
 * access. A grant names the support ticket and runs for a limited number of hours; vendor staff
 * cannot open a session as any of this organisation's users without one, and the session they get
 * is read-only. Each grant lists the sessions that were opened under it, so the customer can see
 * who looked, when and for how long.
 */
#[Title('Support access')]
#[Layout('layouts.app')]
final class SupportAccess extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $ticketReference = '';

    public string $reason = '';

    public string $durationHours = '4';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.support_access.manage');
    }

    public function grant(): void
    {
        $this->authorizePermission('core.support_access.manage');
        $this->resetErrorBag();

        $this->validate(['durationHours' => ['required', 'integer']]);

        try {
            app(GrantSupportAccessAction::class)->execute(new GrantSupportAccessData(
                tenantId: $this->school->tenant_id,
                grantedByUserId: (int) auth()->id(),
                ticketReference: $this->ticketReference,
                reason: $this->reason,
                durationHours: (int) $this->durationHours,
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->reset('ticketReference', 'reason');
        $this->toast(__('Support access granted.'));
    }

    public function revoke(int $grantId): void
    {
        $this->authorizePermission('core.support_access.manage');

        $grant = SupportAccessGrant::query()->where('tenant_id', $this->school->tenant_id)->findOrFail($grantId);
        app(RevokeSupportAccessAction::class)->execute($grant->id, (int) auth()->id());

        $this->toast(__('Support access withdrawn.'));
    }

    public function render(): View
    {
        $grants = SupportAccessGrant::query()->with('grantedBy:id,name')->where('tenant_id', $this->school->tenant_id)->orderByDesc('id')->limit(30)->get();

        return view('core::support-access.index', [
            'grants' => $grants,
            'sessions' => ImpersonationSession::query()->with(['impersonator:id,name', 'impersonated:id,name'])
                ->whereIn('access_grant_id', $grants->pluck('id'))->orderByDesc('started_at')->limit(50)->get()->groupBy('access_grant_id'),
            'maxHours' => GrantSupportAccessAction::MAX_HOURS,
        ]);
    }
}
