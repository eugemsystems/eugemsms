<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\DataObjects\ClearChequeData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\Events\ChequeCleared;
use Modules\Finance\Domain\Support\AllocationStrategy;
use Modules\Finance\Domain\Support\InvoiceAllocationEngine;
use Modules\Finance\Models\ReceiptTender;

/**
 * ACT-ClearCheque (Book B FIN-04 §5/BR-FIN-04-018, AC-FIN-04-010). A
 * cheque tender does not reduce the learner's balance at receipt time
 * — clearing is what finally settles the invoices the receipt was
 * for, moving the amount out of the uncleared-cheque holding account
 * and into the specific debtor accounts it pays off.
 */
final class ClearChequeAction extends Action
{
    public function __construct(
        private readonly InvoiceAllocationEngine $allocationEngine,
        private readonly PostJournalAction $postJournal,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(ClearChequeData $data): ReceiptTender
    {
        $tender = ReceiptTender::with('receipt')->findOrFail($data->receiptTenderId);

        if ($tender->is_cleared) {
            return $tender;
        }

        $receipt = $tender->receipt;

        return $this->transaction(function () use ($tender, $receipt, $data): ReceiptTender {
            $strategy = $data->allocationStrategy ?? AllocationStrategy::from(
                (string) $this->settings->get('finance.default_allocation_strategy', new ScopeChain(schoolId: $receipt->school_id))
            );

            $result = $this->allocationEngine->allocate(
                $receipt,
                $receipt->student_id,
                $tender->amount_minor,
                $tender->currency,
                $strategy,
                $data->clearedByUserId,
            );

            $currency = Currency::from($tender->currency);
            $journalLines = [];

            foreach ($result['allocations'] as $allocation) {
                $journalLines[] = new JournalLineData(
                    accountId: $data->unclearedChequeAccountId,
                    direction: 'DR',
                    amount: Money::of($allocation->amount_minor, $currency),
                    narration: 'Cheque cleared',
                );

                $journalLines[] = new JournalLineData(
                    accountId: $allocation->component->debtor_account_id,
                    direction: 'CR',
                    amount: Money::of($allocation->amount_minor, $currency),
                    subledgerType: 'guardian',
                    subledgerId: $allocation->invoice->billed_party_id,
                    narration: 'Cheque cleared',
                );
            }

            if ($journalLines !== []) {
                $this->postJournal->execute(new PostJournalData(
                    schoolId: $receipt->school_id,
                    academicYearId: $receipt->academic_year_id,
                    termId: $receipt->term_id,
                    journalType: 'CHEQUE_CLEARED',
                    narration: "Cheque cleared — receipt {$receipt->receipt_number}",
                    lines: $journalLines,
                    effectiveAt: Carbon::now(),
                    postedByUserId: $data->clearedByUserId,
                    sourceType: 'receipt_tender',
                    sourceId: $tender->id,
                ));
            }

            $tender->update(['is_cleared' => true, 'cleared_at' => Carbon::now()]);

            $settledMinor = $tender->amount_minor - $result['leftoverMinor'];

            $receipt->update([
                'allocated_minor' => $receipt->allocated_minor + $settledMinor,
                'unallocated_minor' => max(0, $receipt->unallocated_minor - $settledMinor),
            ]);

            event(new ChequeCleared($tender));

            return $tender;
        });
    }
}
