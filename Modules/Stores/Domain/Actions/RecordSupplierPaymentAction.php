<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Models\BankAccount;
use Modules\Stores\Domain\DataObjects\RecordSupplierPaymentData;
use Modules\Stores\Domain\Events\PaymentApproved;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierInvoice;
use Modules\Stores\Models\SupplierPayment;
use Modules\Stores\Models\SupplierPaymentAllocation;

/**
 * ACT-RecordSupplierPayment (Book H1 FIN-08 §6 ⭐/§3/BR-FIN-08-019/020/
 * 021/AC-FIN-08-008). Settles the FULL remaining balance of every
 * selected invoice in one run (partial settlement is a documented,
 * deliberate scope boundary for this pass). Refuses if the payment's
 * own approver approved ANY of the invoices being paid — the same
 * maker-checker split `ApproveManualJournalAction` enforces elsewhere
 * in this codebase, applied here across the invoice/payment boundary
 * specifically (BR-FIN-08-020). Posts ONE journal per payment: `Dr
 * Creditors` for the gross total, `Cr Bank` for what actually leaves
 * the account, `Cr Withholding Tax Payable` for what was withheld —
 * exactly the three-line shape in §3's own diagram.
 */
final class RecordSupplierPaymentAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(RecordSupplierPaymentData $data): SupplierPayment
    {
        $supplier = Supplier::findOrFail($data->supplierId);
        $invoices = SupplierInvoice::whereIn('id', $data->invoiceIds)->get();

        if ($invoices->count() !== count($data->invoiceIds)) {
            throw ValidationException::withMessages(['invoiceIds' => 'One or more invoices could not be found.']);
        }

        foreach ($invoices as $invoice) {
            if ($invoice->supplier_id !== $supplier->id) {
                throw ValidationException::withMessages(['invoiceIds' => "Invoice #{$invoice->id} does not belong to supplier #{$supplier->id}."]);
            }

            if ($invoice->status !== 'approved' && $invoice->status !== 'partially_paid') {
                throw new InvalidStateTransitionException(
                    "Invoice #{$invoice->id} must be approved before payment (currently {$invoice->status}).",
                    ['invoice_id' => $invoice->id],
                );
            }

            if ($invoice->approved_by === $data->approvedByUserId) {
                throw new InvalidStateTransitionException(
                    "The payment approver must be a different user from whoever approved invoice #{$invoice->id} (BR-FIN-08-020).",
                    ['invoice_id' => $invoice->id],
                );
            }
        }

        $grossMinor = (int) $invoices->sum('total_minor');
        $withholdingMinor = (int) $invoices->sum('withholding_minor');
        $netMinor = $grossMinor - $withholdingMinor;

        if ($withholdingMinor > 0 && $data->withholdingPayableAccountId === null) {
            throw ValidationException::withMessages([
                'withholdingPayableAccountId' => 'A withholding tax payable account is required when any selected invoice carries withholding.',
            ]);
        }

        $currency = Currency::from($invoices->first()->currency);
        $bankAccount = BankAccount::findOrFail($data->bankAccountId);

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'supplier_payment',
            allocatedByUserId: $data->approvedByUserId,
            termId: $data->termId,
        ));

        return $this->transaction(function () use ($data, $supplier, $invoices, $currency, $bankAccount, $number, $grossMinor, $withholdingMinor, $netMinor): SupplierPayment {
            $journalLines = [
                new JournalLineData(
                    accountId: $supplier->control_account_id,
                    direction: 'DR',
                    amount: Money::of($grossMinor, $currency),
                    subledgerType: 'supplier',
                    subledgerId: $supplier->id,
                ),
                new JournalLineData(accountId: $bankAccount->gl_account_id, direction: 'CR', amount: Money::of($netMinor, $currency)),
            ];

            if ($withholdingMinor > 0) {
                $journalLines[] = new JournalLineData(accountId: $data->withholdingPayableAccountId, direction: 'CR', amount: Money::of($withholdingMinor, $currency));
            }

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $invoices->first()->academic_year_id,
                termId: $data->termId,
                journalType: 'SUPPLIER_PAYMENT',
                narration: "Supplier payment {$number->formatted_number} — {$supplier->name}",
                lines: $journalLines,
                effectiveAt: $data->paymentDate,
                postedByUserId: $data->approvedByUserId,
                sourceType: 'supplier_payment',
                sourceId: null,
            ));

            $payment = SupplierPayment::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'payment_number' => $number->formatted_number,
                'supplier_id' => $supplier->id,
                'payment_date' => $data->paymentDate->toDateString(),
                'payment_method' => $data->paymentMethod,
                'bank_account_id' => $bankAccount->id,
                'reference' => $data->reference,
                'gross_minor' => $grossMinor,
                'withholding_minor' => $withholdingMinor,
                'net_minor' => $netMinor,
                'currency' => $currency->value,
                'status' => 'approved',
                'approved_by' => $data->approvedByUserId,
                'journal_id' => $journal->id,
                'batch_id' => $data->batchId,
            ]);

            foreach ($invoices as $invoice) {
                SupplierPaymentAllocation::create([
                    'school_id' => $data->schoolId,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount_minor' => $invoice->balance_minor,
                    'currency' => $invoice->currency,
                    'allocated_at' => Carbon::now(),
                    'allocated_by' => $data->approvedByUserId,
                ]);

                $invoice->update([
                    'paid_minor' => $invoice->paid_minor + $invoice->balance_minor,
                    'balance_minor' => 0,
                    'status' => 'paid',
                ]);
            }

            event(new PaymentApproved($payment));

            return $payment;
        });
    }
}
