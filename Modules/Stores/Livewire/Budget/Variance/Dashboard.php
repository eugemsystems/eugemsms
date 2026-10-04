<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Budget\Variance;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Domain\Actions\CheckBudgetVarianceAction;
use Modules\Stores\Domain\Actions\RecalculateBudgetLineActualsAction;
use Modules\Stores\Models\Budget;
use Modules\Stores\Models\BudgetLine;

/**
 * `Budget\Variance\Dashboard` (Book H1 FIN-11 §5 ⭐, `budget.view`,
 * AC-FIN-11-005/007). "Recalculate actuals" is the ONLY control that
 * ever changes `actual_minor`, and it only ever re-sums real
 * `journal_lines` (BR-FIN-11-008) — there is no field anywhere on
 * this screen to type one in by hand.
 */
#[Title('Budget variance')]
#[Layout('layouts.app')]
final class Dashboard extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $budgetId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('budget.view');
    }

    public function recalculate(int $lineId): void
    {
        app(RecalculateBudgetLineActualsAction::class)->execute($lineId);
        $this->toast(__('Actuals recalculated from the GL.'));
    }

    public function checkVariance(): void
    {
        $exceeded = app(CheckBudgetVarianceAction::class)->execute($this->school->id);
        $this->toast(__(':n line(s) beyond the variance threshold.', ['n' => $exceeded->count()]));
    }

    public function render(): View
    {
        $lines = BudgetLine::where('school_id', $this->school->id)
            ->when($this->budgetId !== null, fn ($q) => $q->where('budget_id', $this->budgetId))
            ->get();

        $accounts = Account::whereIn('id', $lines->pluck('account_id'))->get()->keyBy('id');
        $costCentres = CostCentre::whereIn('id', $lines->pluck('cost_centre_id'))->get()->keyBy('id');

        return view('stores::budget.variance.dashboard', [
            'budgets' => Budget::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'lines' => $lines,
            'accounts' => $accounts,
            'costCentres' => $costCentres,
        ]);
    }
}
