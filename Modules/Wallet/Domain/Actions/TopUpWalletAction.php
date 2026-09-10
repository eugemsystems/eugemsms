<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Wallet\Domain\DataObjects\TopUpWalletData;
use Modules\Wallet\Domain\Events\WalletToppedUp;
use Modules\Wallet\Models\StudentWallet;
use Modules\Wallet\Models\WalletTransaction;

/**
 * ACT-TopUpWallet (Book H3 FIN-14 §3 ⭐/BR-FIN-14-001/003
 * (AC-FIN-14-001)). `Dr` the clearing/bank account, `Cr` the wallet's
 * own liability account — never income. `WalletTransaction` is the
 * append-only source of truth; `student_wallets.balance_minor` is
 * updated as the cache it's documented to be.
 */
final class TopUpWalletAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(TopUpWalletData $data): WalletTransaction
    {
        $wallet = StudentWallet::findOrFail($data->walletId);
        $currency = Currency::from($wallet->currency);
        $amount = Money::of($data->amountMinor, $currency);

        return $this->transaction(function () use ($wallet, $data, $amount, $currency): WalletTransaction {
            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $wallet->school_id,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'WALLET_TOPUP',
                narration: "Wallet top-up — student #{$wallet->student_id}",
                lines: [
                    new JournalLineData(accountId: $data->clearingAccountId, direction: 'DR', amount: $amount),
                    new JournalLineData(
                        accountId: $wallet->liability_account_id, direction: 'CR', amount: $amount,
                        subledgerType: 'student', subledgerId: $wallet->student_id,
                    ),
                ],
                effectiveAt: Carbon::now(),
                postedByUserId: $data->performedByUserId,
                sourceType: 'student_wallet',
                sourceId: $wallet->id,
            ));

            $newBalance = $wallet->balance_minor + $data->amountMinor;

            $transaction = WalletTransaction::create([
                'school_id' => $wallet->school_id,
                'term_id' => $data->termId,
                'wallet_id' => $wallet->id,
                'transaction_type' => 'topup',
                'direction' => 'in',
                'amount_minor' => $data->amountMinor,
                'balance_after_minor' => $newBalance,
                'currency' => $currency->value,
                'receipt_id' => $data->receiptId,
                'journal_id' => $journal->id,
                'performed_by' => $data->performedByUserId,
                'occurred_at' => Carbon::now(),
            ]);

            $wallet->update(['balance_minor' => $newBalance, 'last_transaction_at' => Carbon::now()]);

            event(new WalletToppedUp($transaction));

            return $transaction;
        });
    }
}
