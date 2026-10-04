<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Budget\Commitments;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Models\BudgetCommitment;

/**
 * `Budget\Commitments\Index` (Book H1 FIN-11 §5 ⭐, `budget.view`).
 * Read-only — open and partially-released commitments by line and
 * age, the real control `CreateBudgetCommitmentAction`/
 * `ReleaseCommitmentAction` maintain (BR-FIN-11-004/005/007).
 */
#[Title('Budget commitments')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('budget.view');
    }

    public function render(): View
    {
        return view('stores::budget.commitments.index', [
            'commitments' => BudgetCommitment::with('budgetLine')
                ->where('school_id', $this->school->id)
                ->whereIn('status', ['open', 'partially_released'])
                ->orderBy('committed_at')
                ->get(),
        ]);
    }
}
