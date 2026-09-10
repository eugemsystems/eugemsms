<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\ReverseJournalData;
use Modules\Finance\Domain\DataObjects\VoidReceiptData;
use Modules\Finance\Domain\Events\ReceiptVoided;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\Receipt;

/**
 * ACT-VoidReceipt (Book B FIN-04 §5/BR-FIN-04-017, AC-FIN-04-008). The
 * original receipt is never edited — its journal is reversed and
 * every allocation is reversed (recorded, not deleted), restoring
 * each settled invoice's balance.
 */
final class VoidReceiptAction extends Action
{
    public function __construct(
        private readonly ReverseJournalAction $reverseJournal,
    ) {}

    public function execute(VoidReceiptData $data): Receipt
    {
        $receipt = Receipt::with('allocations')->findOrFail($data->receiptId);

        return $this->transaction(function () use ($receipt, $data): Receipt {
            $reversal = null;

            if ($receipt->journal_id !== null) {
                $reversal = $this->reverseJournal->execute(new ReverseJournalData(
                    journalId: $receipt->journal_id,
                    reason: $data->reason,
                    reversedByUserId: $data->voidedByUserId,
                ));
            }

            foreach ($receipt->allocations as $allocation) {
                if ($allocation->isReversed() || $allocation->invoice_id === null) {
                    continue;
                }

                $invoice = Invoice::findOrFail($allocation->invoice_id);
                $newBalance = $invoice->balance_minor + $allocation->amount_minor;

                $invoice->update([
                    'paid_minor' => $invoice->paid_minor - $allocation->amount_minor,
                    'balance_minor' => $newBalance,
                    'status' => $newBalance >= $invoice->net_minor ? 'issued' : 'partially_paid',
                ]);

                $allocation->update([
                    'reversed_at' => Carbon::now(),
                    'reversed_by' => $data->voidedByUserId,
                    'reversal_reason' => $data->reason,
                ]);
            }

            $receipt->update([
                'status' => 'voided',
                'voided_at' => Carbon::now(),
                'void_reason' => $data->reason,
                'void_journal_id' => $reversal?->id,
            ]);

            event(new ReceiptVoided($receipt));

            return $receipt;
        });
    }
}
