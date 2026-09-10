<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Wallet\Domain\DataObjects\ProcessTermEndWalletData;
use Modules\Wallet\Domain\Events\TermEndBalanceProcessed;
use Modules\Wallet\Models\StudentWallet;
use Modules\Wallet\Models\WalletTransaction;

/**
 * ACT-ProcessTermEndWallet (Book H3 FIN-14 §3/BR-FIN-14-015 ⭐
 * (AC-FIN-14-006)). Three policies, and — deliberately — no fourth:
 * `carry_forward` (a no-op; the balance simply persists into next
 * term), `refund` (`Dr` wallet liability, `Cr` a refund clearing
 * account — money physically leaving), `transfer_to_fees` (`Dr`
 * wallet liability, `Cr` Fee Debtors subledgered to the student,
 * mirroring `Modules\Payroll`'s own staff-child fee-offset journal
 * shape exactly). Recognising the balance as income is not a
 * reachable code path — there is no branch for it.
 */
final class ProcessTermEndWalletAction extends Action
{
    private const array VALID_POLICIES = ['carry_forward', 'refund', 'transfer_to_fees'];

    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(ProcessTermEndWalletData $data): StudentWallet
    {
        if (! in_array($data->policy, self::VALID_POLICIES, true)) {
            throw ValidationException::withMessages(['policy' => 'Term-end policy must be one of: '.implode(', ', self::VALID_POLICIES).'.']);
        }

        $wallet = StudentWallet::findOrFail($data->walletId);

        if ($wallet->balance_minor === 0 || $data->policy === 'carry_forward') {
            event(new TermEndBalanceProcessed($wallet, $data->policy, $wallet->balance_minor));

            return $wallet;
        }

        $currency = Currency::from($wallet->currency);
        $amount = Money::of($wallet->balance_minor, $currency);

        return $this->transaction(function () use ($wallet, $data, $currency, $amount): StudentWallet {
            $creditAccountId = $data->policy === 'refund' ? $data->refundClearingAccountId : $data->feeDebtorsAccountId;

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $wallet->school_id,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'WALLET_TERM_END',
                narration: "Wallet term-end {$data->policy} — student #{$wallet->student_id}",
                lines: [
                    new JournalLineData(accountId: $wallet->liability_account_id, direction: 'DR', amount: $amount, subledgerType: 'student', subledgerId: $wallet->student_id),
                    new JournalLineData(accountId: $creditAccountId, direction: 'CR', amount: $amount, subledgerType: $data->policy === 'transfer_to_fees' ? 'student' : null, subledgerId: $data->policy === 'transfer_to_fees' ? $wallet->student_id : null),
                ],
                effectiveAt: Carbon::now(),
                postedByUserId: $data->performedByUserId,
                sourceType: 'student_wallet',
                sourceId: $wallet->id,
            ));

            $processedAmount = $wallet->balance_minor;

            WalletTransaction::create([
                'school_id' => $wallet->school_id,
                'term_id' => $data->termId,
                'wallet_id' => $wallet->id,
                'transaction_type' => $data->policy === 'refund' ? 'term_end_refund' : 'term_end_carry',
                'direction' => 'out',
                'amount_minor' => $processedAmount,
                'balance_after_minor' => 0,
                'currency' => $currency->value,
                'journal_id' => $journal->id,
                'performed_by' => $data->performedByUserId,
                'occurred_at' => Carbon::now(),
            ]);

            $wallet->update(['balance_minor' => 0, 'last_transaction_at' => Carbon::now()]);

            event(new TermEndBalanceProcessed($wallet->fresh(), $data->policy, $processedAmount));

            return $wallet->fresh();
        });
    }
}
