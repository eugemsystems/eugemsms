<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\JournalLine;
use Modules\Wallet\Domain\Events\WalletReconciliationVariance;
use Modules\Wallet\Models\StudentWallet;

/**
 * ACT-ReconcileWalletLiability (Book H3 FIN-14 §5 ⭐/BR-FIN-14-002/
 * 019 (AC-FIN-14-008)). The sum of every active wallet's own
 * `balance_minor` cache must equal the TRUE general-ledger balance of
 * the liability account(s) those wallets reference — computed fresh
 * from `journal_lines` (`Cr` minus `Dr`, a liability's natural
 * credit balance), never from a second cached figure. A school with
 * one control account for every wallet is the common case; this
 * still works correctly if wallets are ever split across several
 * liability accounts, by summing each account's own true balance.
 */
final class ReconcileWalletLiabilityAction extends Action
{
    /**
     * @return array{sum_of_balances_minor: int, liability_account_balance_minor: int, variance_minor: int}
     */
    public function execute(int $schoolId): array
    {
        $wallets = StudentWallet::where('school_id', $schoolId)->where('status', '!=', 'closed')->get();
        $sumOfBalances = (int) $wallets->sum('balance_minor');

        $liabilityAccountIds = $wallets->pluck('liability_account_id')->unique();
        $liabilityBalance = $this->trueLiabilityBalance($schoolId, $liabilityAccountIds);

        $variance = $sumOfBalances - $liabilityBalance;

        if ($variance !== 0) {
            event(new WalletReconciliationVariance($schoolId, $sumOfBalances, $liabilityBalance));
        }

        return [
            'sum_of_balances_minor' => $sumOfBalances,
            'liability_account_balance_minor' => $liabilityBalance,
            'variance_minor' => $variance,
        ];
    }

    /**
     * @param  Collection<int, int>  $accountIds
     */
    private function trueLiabilityBalance(int $schoolId, Collection $accountIds): int
    {
        if ($accountIds->isEmpty()) {
            return 0;
        }

        $credits = (int) JournalLine::where('school_id', $schoolId)
            ->whereIn('account_id', $accountIds)
            ->where('direction', 'CR')
            ->sum('amount_minor');

        $debits = (int) JournalLine::where('school_id', $schoolId)
            ->whereIn('account_id', $accountIds)
            ->where('direction', 'DR')
            ->sum('amount_minor');

        return $credits - $debits;
    }
}
