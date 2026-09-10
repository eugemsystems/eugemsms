<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\DataObjects\CalculateAccountBalanceData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalLine;

/**
 * ACT-CalculateAccountBalance (Book B FIN-01 §5/BR-FIN-01-022/023).
 * Always computed from source `journal_lines` — the only authoritative
 * balance in the system. `account_balances` is a cache of exactly this
 * computation, never the other way around.
 */
final class CalculateAccountBalanceAction extends Action
{
    protected bool $transactional = false;

    public function execute(CalculateAccountBalanceData $data): Money
    {
        $account = Account::with('accountType')->findOrFail($data->accountId);

        $lines = JournalLine::withoutGlobalScopes()
            ->where('account_id', $data->accountId)
            ->where('currency', $data->currency)
            // `effective_at`'s `date` cast serialises to a full
            // datetime string on write (e.g. "2026-09-09 00:00:00"),
            // which sorts *after* a bare "2026-09-09" cutoff — compare
            // against end-of-day so every stored precision passes.
            ->where('effective_at', '<=', $data->asAt->copy()->endOfDay())
            ->get(['direction', 'amount_minor']);

        $debit = (int) $lines->where('direction', 'DR')->sum('amount_minor');
        $credit = (int) $lines->where('direction', 'CR')->sum('amount_minor');

        $balance = $account->accountType->isDebitNormal() ? $debit - $credit : $credit - $debit;

        return Money::of($balance, Currency::from($data->currency));
    }
}
