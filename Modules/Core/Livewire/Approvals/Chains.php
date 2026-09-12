<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Approvals;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ApprovalChain;
use Modules\Core\Models\School;

/**
 * `Core\Approvals\Chains` (Book A CORE-07 §5, `core.approval.configure`)
 * — lists every chain, all types. No edit/deactivate here: only
 * `CreateApprovalChainAction` exists in the domain layer today (no
 * update action), so a chain is create-only until a future wave adds
 * one — this list correctly reflects that rather than offering a
 * control with nothing behind it.
 */
#[Title('Approval chains')]
#[Layout('layouts.app')]
final class Chains extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.approval.configure');
    }

    public function render(): View
    {
        $chains = ApprovalChain::query()
            ->where('school_id', $this->school->id)
            ->withCount('steps')
            ->orderBy('approvable_type')
            ->orderBy('priority')
            ->get();

        return view('core::approvals.chains', ['chains' => $chains]);
    }
}
