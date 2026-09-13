<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Integrity;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\FindStaleAccountBalancesAction;
use Modules\Finance\Domain\Actions\RebuildAccountBalancesAction;
use Modules\Finance\Domain\DataObjects\FindStaleAccountBalancesData;
use Modules\Finance\Domain\DataObjects\RebuildAccountBalancesData;
use Modules\Finance\Models\Account;

/**
 * `Finance\Integrity\Balances` (Book B FIN-01 §8, `finance.integrity.view`)
 * — compares the `account_balances` cache against the same from-source
 * aggregate `RebuildAccountBalancesAction` itself computes (read-only
 * here, nothing is written unless Rebuild is pressed), surfacing any
 * (account, term, currency) triple where the cache disagrees with
 * source. BR-FIN-01-024/§3: the cache is verified against source, never
 * the other way around. The comparison itself lives in
 * `FindStaleAccountBalancesAction` — Book A Part 1.1's CI rule forbids a
 * raw query-builder call against the `DB` facade anywhere under a
 * module's `Livewire/` directory, with no read/write exception.
 */
#[Title('Balance integrity')]
#[Layout('layouts.app')]
final class Balances extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.integrity.view');
    }

    public function rebuild(): void
    {
        $this->authorizePermission('finance.integrity.view');

        $count = app(RebuildAccountBalancesAction::class)->execute(new RebuildAccountBalancesData(
            schoolId: $this->school->id,
            full: true,
        ));

        $this->toast(__(':count balance rows rebuilt from source.', ['count' => $count]));
    }

    public function render(): View
    {
        $mismatches = app(FindStaleAccountBalancesAction::class)->execute(
            new FindStaleAccountBalancesData(schoolId: $this->school->id),
        );

        $accountsById = Account::query()->whereIn('id', array_column($mismatches, 'account_id'))->get()->keyBy('id');

        return view('finance::integrity.balances', [
            'mismatches' => $mismatches,
            'accountsById' => $accountsById,
        ]);
    }
}
