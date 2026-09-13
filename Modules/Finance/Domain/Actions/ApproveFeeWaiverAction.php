<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\DataObjects\ApproveFeeWaiverData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Models\FeeWaiver;
use Modules\Finance\Models\Invoice;

/**
 * ACT-ApproveFeeWaiver (Book B FIN-03 §4/BR-FIN-03-012/013). Posts
 * `Dr contraAccountId / Cr debtorAccountId` — a write-off's
 * `contraAccountId` is the school's Bad Debt Expense account; a
 * waiver's is whichever waiver/discount-expense account the school
 * uses instead. Which specific accounts those are is an explicit
 * parameter here, never auto-provisioned (the same "every GL account
 * decision is an explicit parameter" rule FIN-04's till/suspense
 * accounts already follow) — this action enforces the double-entry
 * shape, not which two accounts fill it.
 *
 * The invoice cache has no separate "waived" column (Book B FIN-03 §2
 * lists only `paid_minor`/`credited_minor`/`written_off_minor`/
 * `balance_minor`) — a waiver moves `written_off_minor` exactly like a
 * write-off does; both remove the amount from what's being chased,
 * which is what that cache column actually tracks. `type` still
 * distinguishes why, and which GL account, for reporting
 * (BR-FIN-03-013's "reported separately from collections").
 */
final class ApproveFeeWaiverAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(ApproveFeeWaiverData $data): FeeWaiver
    {
        $waiver = FeeWaiver::findOrFail($data->feeWaiverId);

        if ($waiver->status !== 'pending') {
            throw new InvalidStateTransitionException(
                "Only a pending waiver/write-off can be approved — this one is {$waiver->status}.",
                ['fee_waiver_id' => $waiver->id],
            );
        }

        if ($data->approvedByUserId === $waiver->requested_by) {
            throw new InvalidStateTransitionException(
                'A waiver/write-off cannot be approved by the same user who requested it.',
                ['fee_waiver_id' => $waiver->id],
            );
        }

        $term = Term::findOrFail($waiver->term_id);
        $currency = Currency::from($waiver->currency);
        $amount = Money::of($waiver->amount_minor, $currency);
        $label = $waiver->type === 'write_off' ? 'Write-off' : 'Waiver';

        return $this->transaction(function () use ($waiver, $data, $term, $amount, $label): FeeWaiver {
            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $waiver->school_id,
                academicYearId: $term->academic_year_id,
                termId: $term->id,
                journalType: strtoupper($waiver->type),
                narration: "{$label}: {$waiver->reason}",
                lines: [
                    new JournalLineData(
                        accountId: $data->contraAccountId,
                        direction: 'DR',
                        amount: $amount,
                        subledgerType: 'student',
                        subledgerId: $waiver->student_id,
                        narration: $waiver->reason,
                    ),
                    new JournalLineData(
                        accountId: $data->debtorAccountId,
                        direction: 'CR',
                        amount: $amount,
                        subledgerType: 'student',
                        subledgerId: $waiver->student_id,
                        narration: $waiver->reason,
                    ),
                ],
                effectiveAt: Carbon::now(),
                postedByUserId: $data->approvedByUserId,
                sourceType: 'fee_waiver',
                sourceId: $waiver->id,
            ));

            $waiver->update([
                'status' => 'posted',
                'approved_by' => $data->approvedByUserId,
                'journal_id' => $journal->id,
            ]);

            if ($waiver->invoice_id !== null) {
                $invoice = Invoice::findOrFail($waiver->invoice_id);
                $newBalance = $invoice->balance_minor - $waiver->amount_minor;

                $invoice->update([
                    'written_off_minor' => $invoice->written_off_minor + $waiver->amount_minor,
                    'balance_minor' => $newBalance,
                    'status' => $newBalance <= 0 ? 'written_off' : $invoice->status,
                ]);
            }

            return $waiver;
        });
    }
}
