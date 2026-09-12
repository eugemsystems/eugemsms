<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Approvals;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Approvals\CancelApprovalRequestAction;
use Modules\Core\Domain\Actions\Approvals\ResubmitApprovalAction;
use Modules\Core\Domain\DataObjects\Approvals\CancelApprovalRequestData;
use Modules\Core\Domain\DataObjects\Approvals\ResubmitApprovalData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\School;

/**
 * `Core\Approvals\MyRequests` (Book A CORE-07 §5 — own). Every request
 * the signed-in user has raised in this school, whatever its status.
 */
#[Title('My requests')]
#[Layout('layouts.app')]
final class MyRequests extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function cancel(int $requestId): void
    {
        try {
            app(CancelApprovalRequestAction::class)->execute(new CancelApprovalRequestData(
                requestId: $requestId,
                cancelledByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Request cancelled.'));
    }

    public function resubmit(int $requestId): void
    {
        try {
            app(ResubmitApprovalAction::class)->execute(new ResubmitApprovalData($requestId));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Resubmitted for approval.'));
    }

    public function render(): View
    {
        $requests = ApprovalRequest::query()
            ->where('school_id', $this->school->id)
            ->where('requested_by', (int) Auth::id())
            ->orderByDesc('requested_at')
            ->paginate(15);

        return view('core::approvals.my-requests', ['requests' => $requests]);
    }
}
