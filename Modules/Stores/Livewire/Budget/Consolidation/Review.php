<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Budget\Consolidation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\ApproveBudgetAction;
use Modules\Stores\Domain\Actions\ConsolidateBudgetAction;
use Modules\Stores\Models\Budget;

/**
 * `Budget\Consolidation\Review` (Book H1 FIN-11 §5, `budget.consolidate`).
 * `ConsolidateBudgetAction` sums submitted lines into income/expense/
 * surplus by each line's own account type — never a sign convention
 * this screen could get backwards. The preparer cannot approve their
 * own budget; `ApproveBudgetAction` enforces it.
 */
#[Title('Budget consolidation')]
#[Layout('layouts.app')]
final class Review extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $boardApprovedOn = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('budget.consolidate');
    }

    public function consolidate(int $budgetId): void
    {
        app(ConsolidateBudgetAction::class)->execute($budgetId);
        $this->toast(__('Budget consolidated.'));
    }

    public function approve(int $budgetId): void
    {
        try {
            app(ApproveBudgetAction::class)->execute($budgetId, (int) auth()->id(), Carbon::now());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Budget approved.'));
    }

    public function render(): View
    {
        return view('stores::budget.consolidation.review', [
            'budgets' => Budget::where('school_id', $this->school->id)->orderByDesc('id')->get(),
        ]);
    }
}
