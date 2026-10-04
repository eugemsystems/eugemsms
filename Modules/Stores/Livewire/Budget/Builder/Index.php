<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Budget\Builder;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Domain\Actions\CreateBudgetAction;
use Modules\Stores\Domain\Actions\ReviseBudgetAction;
use Modules\Stores\Domain\Actions\SubmitBudgetLineAction;
use Modules\Stores\Domain\DataObjects\CreateBudgetData;
use Modules\Stores\Domain\DataObjects\SubmitBudgetLineData;
use Modules\Stores\Models\Budget;
use Modules\Stores\Models\BudgetLine;

/**
 * `Budget\Builder\Index` (Book H1 FIN-11 §5, `budget.manage` for the
 * full chart, `budget.submit` for a department's own cost centre).
 * Also folds the spec's separate "Departmental submission" screen —
 * both read/write `SubmitBudgetLineAction` the same way, differing
 * only in which cost centres a viewer may touch, so a second screen
 * would only duplicate this one's form; a `budget.submit`-only viewer
 * simply doesn't see `budget.manage`'s create-budget/revise controls.
 */
#[Title('Budget builder')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $budgetId = null;

    public string $name = '';

    public string $budgetType = 'operating';

    public string $periodBasis = 'annual';

    public ?int $accountId = null;

    public ?int $costCentreId = null;

    public string $annualAmountMinor = '';

    public ?string $basisNote = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('budget.submit');
    }

    public function createBudget(): void
    {
        $this->authorizePermission('budget.manage');

        $this->validate(['name' => ['required', 'string', 'max:150']]);

        $yearId = SessionContext::yearId();

        if ($yearId === null) {
            $this->toast(__('No current academic year is set.'), 'danger');

            return;
        }

        $budget = app(CreateBudgetAction::class)->execute(new CreateBudgetData(
            schoolId: $this->school->id,
            academicYearId: $yearId,
            name: $this->name,
            budgetType: $this->budgetType,
            periodBasis: $this->periodBasis,
            currency: 'USD',
            preparedByUserId: (int) auth()->id(),
        ));

        $this->budgetId = $budget->id;
        $this->reset(['name']);
        $this->toast(__('Budget created.'));
    }

    public function revise(int $budgetId): void
    {
        $this->authorizePermission('budget.manage');

        try {
            $revision = app(ReviseBudgetAction::class)->execute($budgetId, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->budgetId = $revision->id;
        $this->toast(__('New revision created.'));
    }

    public function submitLine(): void
    {
        $this->validate([
            'budgetId' => ['required', 'integer'],
            'accountId' => ['required', 'integer'],
            'costCentreId' => ['required', 'integer'],
            'annualAmountMinor' => ['required', 'integer', 'min:0'],
        ]);

        try {
            app(SubmitBudgetLineAction::class)->execute(new SubmitBudgetLineData(
                budgetId: (int) $this->budgetId,
                accountId: (int) $this->accountId,
                costCentreId: (int) $this->costCentreId,
                annualAmountMinor: (int) $this->annualAmountMinor,
                basisNote: $this->basisNote,
            ));
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['annualAmountMinor', 'basisNote']);
        $this->toast(__('Line saved.'));
    }

    public function render(): View
    {
        $budget = $this->budgetId !== null ? Budget::find($this->budgetId) : null;

        return view('stores::budget.builder.index', [
            'budgets' => Budget::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'lines' => $budget !== null ? BudgetLine::where('budget_id', $budget->id)->get() : collect(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
        ]);
    }
}
