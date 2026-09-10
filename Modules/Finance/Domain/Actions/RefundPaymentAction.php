<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InsufficientBalanceException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\DataObjects\CalculateSubledgerBalanceData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\DataObjects\RefundPaymentData;
use Modules\Finance\Domain\Events\RefundIssued;
use Modules\Finance\Models\Journal;

/**
 * ACT-RefundPayment (Book B FIN-05 §6/BR-FIN-05-015). Requires an
 * available credit balance before it will post anything — this
 * action IS the approval-chain gate for this pass (the caller has
 * already obtained `approvedByUserId` out of band; no `CORE-07` chain
 * is wired, the same deferral every other approval gate in this
 * session has made). Where the driver supports disbursement, a real
 * implementation would return the refund to the original wallet
 * instead of a plain bank debit — `disburse()` exists on the driver
 * contract but is not called here.
 */
final class RefundPaymentAction extends Action
{
    public function __construct(
        private readonly CalculateSubledgerBalanceAction $calculateBalance,
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(RefundPaymentData $data): Journal
    {
        $currency = Currency::from($data->currency);

        $available = $this->calculateBalance->execute(new CalculateSubledgerBalanceData(
            schoolId: $data->schoolId,
            subledgerType: 'student',
            subledgerId: $data->studentId,
            currency: $data->currency,
            asAt: Carbon::now(),
            accountId: $data->creditBalanceAccountId,
        ));

        if ($available->minor < $data->amountMinor) {
            throw new InsufficientBalanceException(
                "Student [{$data->studentId}] has a credit balance of {$available->minor} minor units, less than the requested refund of {$data->amountMinor}.",
                ['available_minor' => $available->minor, 'requested_minor' => $data->amountMinor],
            );
        }

        return $this->transaction(function () use ($data, $currency): Journal {
            $amount = Money::of($data->amountMinor, $currency);

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'REFUND',
                narration: "Refund: {$data->reason}",
                lines: [
                    new JournalLineData(
                        accountId: $data->creditBalanceAccountId,
                        direction: 'DR',
                        amount: $amount,
                        subledgerType: 'student',
                        subledgerId: $data->studentId,
                        narration: $data->reason,
                    ),
                    new JournalLineData(
                        accountId: $data->bankAccountGlId,
                        direction: 'CR',
                        amount: $amount,
                        narration: $data->reason,
                    ),
                ],
                effectiveAt: Carbon::now(),
                postedByUserId: $data->approvedByUserId,
                sourceType: 'refund',
                sourceId: $data->studentId,
            ));

            event(new RefundIssued($journal));

            return $journal;
        });
    }
}
