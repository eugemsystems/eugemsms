<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Approvals;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Approvals\ApprovalEligibilityChecker;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\School;

/**
 * `Core\Approvals\Queue` (Book A CORE-07 §5, "My approvals" — own, no
 * separate permission gate: every assigned user sees their own queue).
 * There is no `approver_id` column to filter by directly — every
 * pending request for the school is checked against
 * `ApprovalEligibilityChecker` (the same eligibility
 * `ApproveStepAction`/etc. enforce) to see whether the signed-in user
 * can act on it right now, directly or via an active delegation.
 */
#[Title('My approvals')]
#[Layout('layouts.app')]
final class Queue extends Component
{
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function render(): View
    {
        $userId = (int) Auth::id();
        $checker = app(ApprovalEligibilityChecker::class);

        $pending = ApprovalRequest::query()
            ->where('school_id', $this->school->id)
            ->where('status', 'pending')
            ->orderBy('requested_at')
            ->get()
            ->filter(fn (ApprovalRequest $request): bool => $checker->canActOn($request, $userId))
            ->values();

        return view('core::approvals.queue', ['requests' => $pending]);
    }
}
