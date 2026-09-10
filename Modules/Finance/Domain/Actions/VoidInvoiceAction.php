<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\ReverseJournalData;
use Modules\Finance\Domain\DataObjects\VoidInvoiceData;
use Modules\Finance\Domain\Exceptions\InvoiceVoidRefusedException;
use Modules\Finance\Models\Invoice;

/**
 * ACT-VoidInvoice (Book B FIN-03 §4/BR-FIN-03-004/005, AC-FIN-03-002/003).
 * The original invoice and its journal are never altered — voiding
 * reverses the journal and marks the invoice `voided`, leaving every
 * other column exactly as issued. Reissuing a replacement is a
 * separate call (typically another `IssueInvoicesForAssignmentAction`);
 * pass its id as `replacementInvoiceId` to link it back here in the
 * same operation.
 */
final class VoidInvoiceAction extends Action
{
    public function __construct(
        private readonly ReverseJournalAction $reverseJournal,
    ) {}

    public function execute(VoidInvoiceData $data): Invoice
    {
        $invoice = Invoice::findOrFail($data->invoiceId);

        if ($invoice->paid_minor > 0) {
            throw InvoiceVoidRefusedException::paymentAllocated($invoice->id, $invoice->paid_minor);
        }

        return $this->transaction(function () use ($invoice, $data): Invoice {
            if ($invoice->journal_id !== null) {
                $this->reverseJournal->execute(new ReverseJournalData(
                    journalId: $invoice->journal_id,
                    reason: $data->reason,
                    reversedByUserId: $data->voidedByUserId,
                ));
            }

            $invoice->update([
                'status' => 'voided',
                'voided_at' => Carbon::now(),
                'void_reason' => $data->reason,
                'replaced_by_invoice_id' => $data->replacementInvoiceId,
            ]);

            return $invoice;
        });
    }
}
