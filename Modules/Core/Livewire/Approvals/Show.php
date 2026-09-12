<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Approvals;

use App\Concerns\Toasts;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Approvals\ApproveStepAction;
use Modules\Core\Domain\Actions\Approvals\CancelApprovalRequestAction;
use Modules\Core\Domain\Actions\Approvals\RejectStepAction;
use Modules\Core\Domain\Actions\Approvals\ReturnStepAction;
use Modules\Core\Domain\DataObjects\Approvals\ApproveStepData;
use Modules\Core\Domain\DataObjects\Approvals\CancelApprovalRequestData;
use Modules\Core\Domain\DataObjects\Approvals\RejectStepData;
use Modules\Core\Domain\DataObjects\Approvals\ReturnStepData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Approvals\ApprovalEligibilityChecker;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\School;

/**
 * `Core\Approvals\Show` (Book A CORE-07 §5, "contextual" — no single
 * blanket permission). Access is granted to the requester, to anyone
 * `ApprovalEligibilityChecker` says can currently act on it, or to a
 * `core.approval.view` holder (an admin/audit override for a request
 * neither party to). Approve/Reject/Return are only offered when
 * eligible right now; `requires_comment` (BR-CORE-07-014) is enforced
 * by the Actions themselves, this just surfaces the same rule as a
 * required field before submitting.
 */
#[Title('Approval request')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public ApprovalRequest $request;

    public string $comment = '';

    public function mount(School $school, ApprovalRequest $request): void
    {
        $this->loadSchool($school);

        abort_unless($request->school_id === $school->id, 403);

        $userId = (int) Auth::id();
        $canView = $request->requested_by === $userId
            || app(ApprovalEligibilityChecker::class)->canActOn($request, $userId)
            || $this->hasPermission('core.approval.view');

        abort_unless($canView, 403);

        $this->request = $request;
    }

    private function hasPermission(string $name): bool
    {
        $user = Auth::user();

        return $user !== null && app(PermissionScopeResolver::class)->has($user, $name, PermissionScope::Own, $this->school->id);
    }

    public function canAct(): bool
    {
        return app(ApprovalEligibilityChecker::class)->canActOn($this->request->fresh(), (int) Auth::id());
    }

    public function approve(): void
    {
        $this->act(fn () => app(ApproveStepAction::class)->execute(new ApproveStepData(
            requestId: $this->request->id,
            actorUserId: (int) Auth::id(),
            comment: $this->comment !== '' ? $this->comment : null,
            ip: request()->ip(),
        )), __('Approved.'));
    }

    public function reject(): void
    {
        $this->act(fn () => app(RejectStepAction::class)->execute(new RejectStepData(
            requestId: $this->request->id,
            actorUserId: (int) Auth::id(),
            comment: $this->comment !== '' ? $this->comment : null,
            ip: request()->ip(),
        )), __('Rejected.'));
    }

    public function returnToRequester(): void
    {
        $this->act(fn () => app(ReturnStepAction::class)->execute(new ReturnStepData(
            requestId: $this->request->id,
            actorUserId: (int) Auth::id(),
            comment: $this->comment !== '' ? $this->comment : null,
            ip: request()->ip(),
        )), __('Returned to the requester.'));
    }

    public function cancel(): void
    {
        $this->act(fn () => app(CancelApprovalRequestAction::class)->execute(new CancelApprovalRequestData(
            requestId: $this->request->id,
            cancelledByUserId: (int) Auth::id(),
            isPrivileged: $this->hasPermission('core.approval.cancel'),
        )), __('Request cancelled.'));
    }

    private function act(Closure $callback, string $successMessage): void
    {
        try {
            $callback();
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->comment = '';
        $this->request = $this->request->fresh();
        $this->toast($successMessage);
    }

    public function render(): View
    {
        $this->request = $this->request->fresh();

        return view('core::approvals.show', [
            'approvable' => $this->request->resolveApprovable(),
            'currentStep' => $this->request->currentStep(),
            'history' => $this->request->actions()->with(['actor', 'onBehalfOf'])->get(),
            'canAct' => $this->canAct(),
            'canCancel' => $this->request->isPending()
                && ($this->request->requested_by === (int) Auth::id() || $this->hasPermission('core.approval.cancel')),
        ]);
    }
}
