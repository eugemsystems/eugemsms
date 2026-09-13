<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Accounts;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CalculateAccountBalanceAction;
use Modules\Finance\Domain\DataObjects\CalculateAccountBalanceData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalLine;

/**
 * `Finance\Accounts\Ledger` (Book B FIN-01 §8, `finance.account.view`)
 * — every line posted to one account, with a running balance. Lines are
 * append-only and dated, so a running balance is a straightforward
 * cumulative sum in `effective_at`/`id` order — computed once per page
 * load over the current page's own rows plus the opening balance as of
 * the page's first row (via `CalculateAccountBalanceAction` "as at" the
 * day before), not the whole account's history on every request.
 */
#[Title('Account ledger')]
#[Layout('layouts.app')]
final class Ledger extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public Account $account;

    public string $currency = 'USD';

    public function mount(School $school, Account $account): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.account.view');
        $this->account = $account;
        $this->currency = $account->currency ?? 'USD';
    }

    public function render(): View
    {
        $query = JournalLine::query()
            ->where('account_id', $this->account->id)
            ->where('currency', $this->currency)
            ->with(['journal', 'costCentre'])
            ->orderBy('effective_at')
            ->orderBy('id');

        $lines = $this->paginateDataTable($query, $this->tableColumns());

        $openingAsAt = $lines->isNotEmpty()
            ? Carbon::parse($lines->items()[0]->effective_at)->subDay()
            : now();

        $opening = app(CalculateAccountBalanceAction::class)->execute(new CalculateAccountBalanceData(
            accountId: $this->account->id,
            currency: $this->currency,
            asAt: $openingAsAt,
        ));

        $running = $opening->minor;
        $isDebitNormal = $this->account->accountType->isDebitNormal();
        $runningBalances = [];

        foreach ($lines->items() as $line) {
            $signed = $line->isDebit() ? $line->amount_minor : -$line->amount_minor;
            $running += $isDebitNormal ? $signed : -$signed;
            $runningBalances[$line->id] = $running;
        }

        return view('finance::accounts.ledger', [
            'lines' => $lines,
            'opening' => $opening,
            'runningBalances' => $runningBalances,
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'effective_at' => ['label' => __('Date'), 'sortable' => true],
            'narration' => ['label' => __('Narration'), 'searchable' => true],
            'direction' => [
                'label' => __('DR/CR'), 'sortable' => true, 'filter' => 'select',
                'options' => ['DR' => __('Debit'), 'CR' => __('Credit')],
            ],
            'amount_minor' => ['label' => __('Amount'), 'sortable' => true],
            'running_balance' => ['label' => __('Running balance')],
        ];
    }
}
