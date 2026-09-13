<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Accounts;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CalculateAccountTreeBalancesAction;
use Modules\Finance\Domain\DataObjects\CalculateAccountTreeBalancesData;
use Modules\Finance\Models\Account;

/**
 * `Finance\Accounts\Tree` (Book B FIN-01 §8, `finance.account.view`) —
 * the chart of accounts, hierarchical, with a live balance per currency.
 * Balance computation itself lives in `CalculateAccountTreeBalancesAction`
 * — Book A Part 1.1's CI rule forbids a raw query-builder call against
 * the `DB` facade anywhere under a module's `Livewire/` directory, with
 * no read/write exception, so even this read-only aggregate belongs in
 * the domain layer.
 */
#[Title('Chart of accounts')]
#[Layout('layouts.app')]
final class Tree extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.account.view');
    }

    public function render(): View
    {
        $accounts = Account::query()
            ->with('accountType')
            ->orderBy('code')
            ->get();

        $balances = app(CalculateAccountTreeBalancesAction::class)->execute(
            new CalculateAccountTreeBalancesData(schoolId: $this->school->id),
        );

        $byParent = $accounts->groupBy('parent_id');

        return view('finance::accounts.tree', [
            'roots' => $byParent->get(null, collect()),
            'byParent' => $byParent,
            'balances' => $balances,
        ]);
    }
}
