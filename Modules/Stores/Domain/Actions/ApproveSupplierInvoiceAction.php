<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\Events\InvoiceMatched;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierInvoice;

/**
 * ACT-ApproveSupplierInvoice (Book H1 FIN-08 §6/BR-FIN-08-015/017/
 * AC-FIN-08-007). An unmatched non-service invoice — no GRN behind it
 * — cannot be approved for payment (BR-FIN-08-017); a `variance`
 * match still can, since that's exactly what "approval is required
 * with the variance displayed" means. Posts `Dr GRN Accrual /
 * Cr Creditors` for the full total — withholding is only ever
 * deducted at payment time (§3's own journal diagram), never here.
 */
final class ApproveSupplierInvoiceAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(int $invoiceId, int $approvedByUserId, int $grnAccrualAccountId): SupplierInvoice
    {
        $invoice = SupplierInvoice::findOrFail($invoiceId);

        if ($invoice->status !== 'received' && $invoice->status !== 'under_review') {
            throw new InvalidStateTransitionException(
                "Invoice #{$invoice->id} must be received or under review to approve (currently {$invoice->status}).",
                ['invoice_id' => $invoice->id, 'status' => $invoice->status],
            );
        }

        if ($invoice->match_status === 'unmatched') {
            throw new InvalidStateTransitionException(
                "Invoice #{$invoice->id} has no matching goods received note and cannot be approved for payment (BR-FIN-08-017).",
                ['invoice_id' => $invoice->id],
            );
        }

        $supplier = Supplier::findOrFail($invoice->supplier_id);
        $currency = Currency::from($invoice->currency);

        return $this->transaction(function () use ($invoice, $supplier, $currency, $approvedByUserId, $grnAccrualAccountId): SupplierInvoice {
            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $invoice->school_id,
                academicYearId: $invoice->academic_year_id,
                termId: $invoice->term_id,
                journalType: 'SUPPLIER_INVOICE',
                narration: "Supplier invoice {$invoice->invoice_number} — {$supplier->name}",
                lines: [
                    new JournalLineData(accountId: $grnAccrualAccountId, direction: 'DR', amount: Money::of($invoice->total_minor, $currency)),
                    new JournalLineData(
                        accountId: $supplier->control_account_id,
                        direction: 'CR',
                        amount: Money::of($invoice->total_minor, $currency),
                        subledgerType: 'supplier',
                        subledgerId: $supplier->id,
                    ),
                ],
                effectiveAt: $invoice->invoice_date,
                postedByUserId: $approvedByUserId,
                sourceType: 'supplier_invoice',
                sourceId: $invoice->id,
            ));

            $invoice->update([
                'status' => 'approved',
                'approved_by' => $approvedByUserId,
                'journal_id' => $journal->id,
            ]);

            event(new InvoiceMatched($invoice));

            return $invoice;
        });
    }
}
