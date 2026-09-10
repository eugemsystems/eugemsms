<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\DataObjects\CalculateSubledgerBalanceData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalLine;

/**
 * ACT-CalculateSubledgerBalance (Book B FIN-01 §5/AC-FIN-01-006). "A
 * learner's balance equals the sum of their ledger lines. There is no
 * other definition of a balance anywhere in the system" (Volume 2
 * Book B §0.3, invariant I-2).
 */
final class CalculateSubledgerBalanceAction extends Action
{
    protected bool $transactional = false;

    public function execute(CalculateSubledgerBalanceData $data): Money
    {
        $isDebitNormal = true;

        if ($data->accountId !== null) {
            $account = Account::with('accountType')->findOrFail($data->accountId);
            $isDebitNormal = $account->accountType->isDebitNormal();
        }

        $query = JournalLine::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('subledger_type', $data->subledgerType)
            ->where('subledger_id', $data->subledgerId)
            ->where('currency', $data->currency)
            // See CalculateAccountBalanceAction for why this compares
            // against end-of-day rather than a bare date string.
            ->where('effective_at', '<=', $data->asAt->copy()->endOfDay());

        if ($data->accountId !== null) {
            $query->where('account_id', $data->accountId);
        }

        $lines = $query->get(['direction', 'amount_minor']);

        $debit = (int) $lines->where('direction', 'DR')->sum('amount_minor');
        $credit = (int) $lines->where('direction', 'CR')->sum('amount_minor');

        $balance = $isDebitNormal ? $debit - $credit : $credit - $debit;

        return Money::of($balance, Currency::from($data->currency));
    }
}
