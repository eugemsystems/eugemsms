<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Approvals;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Approvals\CreateDelegationAction;
use Modules\Core\Domain\Actions\Approvals\RevokeDelegationAction;
use Modules\Core\Domain\DataObjects\Approvals\CreateDelegationData;
use Modules\Core\Domain\DataObjects\Approvals\RevokeDelegationData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ApprovalDelegation;
use Modules\Core\Models\School;

/**
 * `Core\Approvals\Delegations` (Book A CORE-07 §5 — own +
 * `core.approval.delegate_others`). Without that permission you only
 * see and manage delegations you are the delegator of; with it, every
 * delegation in the school.
 */
#[Title('Approval delegations')]
#[Layout('layouts.app')]
final class Delegations extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public bool $showCreateModal = false;

    public string $delegateId = '';

    public string $approvableType = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $reason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    private function canManageOthers(): bool
    {
        $user = Auth::user();

        return $user !== null && app(PermissionScopeResolver::class)->has($user, 'core.approval.delegate_others', PermissionScope::Own, $this->school->id);
    }

    public function openCreateModal(): void
    {
        $this->reset(['delegateId', 'approvableType', 'startsAt', 'endsAt', 'reason']);
        $this->startsAt = now()->format('Y-m-d\TH:i');
        $this->endsAt = now()->addDays(5)->format('Y-m-d\TH:i');
        $this->showCreateModal = true;
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->validate([
            'delegateId' => ['required', 'integer'],
            'approvableType' => ['nullable', 'string', 'max:60'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            app(CreateDelegationAction::class)->execute(new CreateDelegationData(
                schoolId: $this->school->id,
                delegatorId: (int) Auth::id(),
                delegateId: (int) $this->delegateId,
                startsAt: Carbon::parse($this->startsAt),
                endsAt: Carbon::parse($this->endsAt),
                approvableType: $this->approvableType !== '' ? $this->approvableType : null,
                reason: $this->reason !== '' ? $this->reason : null,
                createdByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->showCreateModal = false;
        $this->toast(__('Delegation created.'));
    }

    public function revoke(int $delegationId): void
    {
        $delegation = ApprovalDelegation::withoutGlobalScopes()->findOrFail($delegationId);

        abort_unless($delegation->school_id === $this->school->id, 403);
        abort_unless($delegation->delegator_id === (int) Auth::id() || $this->canManageOthers(), 403);

        app(RevokeDelegationAction::class)->execute(new RevokeDelegationData($delegationId));

        $this->toast(__('Delegation revoked.'));
    }

    public function render(): View
    {
        $query = ApprovalDelegation::query()->where('school_id', $this->school->id);

        if (! $this->canManageOthers()) {
            $query->where('delegator_id', (int) Auth::id());
        }

        return view('core::approvals.delegations', [
            'delegations' => $query->with(['delegator', 'delegate'])->orderByDesc('starts_at')->get(),
            'availableUsers' => $this->school->users()->wherePivot('status', 'active')->orderBy('name')->get(),
            'canManageOthers' => $this->canManageOthers(),
        ]);
    }
}
