<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Fiscal\Domain\DataObjects\RaiseFiscalCreditNoteData;
use Modules\Fiscal\Models\FiscalReceipt;

/**
 * ACT-RaiseFiscalCreditNote (Book H3 FIN-13 §6/BR-FIN-13-013). A
 * voided source receipt never deletes or amends its own fiscal
 * receipt — it raises a brand new `FiscalReceipt` row,
 * `receipt_type = 'credit_note'`, referencing the original via
 * `credited_receipt_id`, through the exact same counter/day/submit
 * pipeline any other fiscal receipt uses.
 */
final class RaiseFiscalCreditNoteAction extends Action
{
    public function __construct(
        private readonly SubmitFiscalReceiptAction $submitReceipt,
    ) {}

    public function execute(RaiseFiscalCreditNoteData $data): FiscalReceipt
    {
        $original = FiscalReceipt::findOrFail($data->originalFiscalReceiptId);

        if ($original->status !== 'accepted') {
            throw new InvalidStateTransitionException(
                "Fiscal receipt #{$original->id} must be accepted before a credit note can reference it (currently {$original->status}).",
                ['fiscal_receipt_id' => $original->id, 'status' => $original->status],
            );
        }

        $creditNote = $this->transaction(function () use ($original, $data): FiscalReceipt {
            $receiptCounter = (int) FiscalReceipt::where('device_id', $original->device_id)
                ->where('receipt_currency', $original->receipt_currency)
                ->max('receipt_counter') + 1;

            $globalCounter = (int) FiscalReceipt::where('device_id', $original->device_id)->max('global_counter') + 1;

            return FiscalReceipt::create([
                'school_id' => $original->school_id,
                'device_id' => $original->device_id,
                'fiscal_day_id' => $original->fiscal_day_id,
                'source_type' => $original->source_type,
                'source_id' => $original->source_id,
                'receipt_type' => 'credit_note',
                'receipt_currency' => $original->receipt_currency,
                'receipt_counter' => $receiptCounter,
                'global_counter' => $globalCounter,
                'invoice_number' => $original->invoice_number,
                'receipt_date' => Carbon::now(),
                'total_minor' => $original->total_minor,
                'tax_breakdown' => $original->tax_breakdown,
                'payment_methods' => $original->payment_methods,
                'buyer_name' => $original->buyer_name,
                'credited_receipt_id' => $original->id,
                'credit_reason' => $data->creditReason,
                'previous_receipt_hash' => $original->receipt_hash,
                'status' => 'queued',
                'payload' => [
                    'invoice_number' => $original->invoice_number,
                    'receipt_counter' => $receiptCounter,
                    'global_counter' => $globalCounter,
                    'currency' => $original->receipt_currency,
                    'total_minor' => $original->total_minor,
                    'tax_breakdown' => $original->tax_breakdown,
                    'credited_fdms_receipt_id' => $original->fdms_receipt_id,
                    'credit_reason' => $data->creditReason,
                ],
            ]);
        });

        $this->submitReceipt->execute($creditNote->id);

        return $creditNote->fresh();
    }
}
