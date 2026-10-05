<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Tenant;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Saas\Domain\Actions\GetAnnouncementsForTenantAction;
use Modules\Saas\Domain\Actions\GetPublicStatusAction;

/**
 * `Saas\Announcements` — school-facing (BR-SAA-02-006). What the vendor has
 * broadcast to the signed-in user's OWN tenant (all-tenant notices plus any
 * that name it) and the current public incidents. Read-only; the tenant is
 * the user's, checked against the school in the URL.
 */
#[Title('Announcements')]
#[Layout('layouts.app')]
final class Announcements extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('subscription.view');

        abort_unless($this->tenantId() !== null, 403);
    }

    private function tenantId(): ?int
    {
        $userTenantId = auth()->user()?->tenant_id;

        return $userTenantId !== null && (int) $userTenantId === (int) $this->school->tenant_id ? (int) $userTenantId : null;
    }

    public function render(): View
    {
        $tenantId = $this->tenantId();

        return view('saas::tenant.announcements', [
            'announcements' => $tenantId === null ? collect() : app(GetAnnouncementsForTenantAction::class)->execute($tenantId),
            'incidents' => app(GetPublicStatusAction::class)->execute()->where('status', '!=', 'resolved'),
        ]);
    }
}
