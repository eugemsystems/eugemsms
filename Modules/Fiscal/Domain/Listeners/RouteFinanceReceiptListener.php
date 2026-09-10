<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Listeners;

use Modules\Finance\Domain\Events\ReceiptPosted;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\ReceiptAllocation;
use Modules\Fiscal\Domain\Actions\RouteReceiptForFiscalisationAction;
use Modules\Fiscal\Domain\DataObjects\RouteReceiptForFiscalisationData;

/**
 * Book H3 FIN-13 §9, closing the retro-fit `Modules\Finance`'s own
 * `receipts` migration docblock names explicitly: `fiscalisation_status`
 * was set to `'queued'` by `CreateReceiptAction` the moment an
 * allocation's `FeeComponent.is_fiscalisable` was true, with nothing
 * to act on it because `FIN-13` didn't exist. This is that action — a
 * real, additive listener on the already-dispatched `ReceiptPosted`
 * event, not an edit to `CreateReceiptAction` itself. A receipt with
 * `fiscalisation_status !== 'queued'` (including a suspense receipt,
 * which has no allocations at all) is silently skipped — that status
 * is `CreateReceiptAction`'s own, already-correct signal.
 */
final class RouteFinanceReceiptListener
{
    public function __construct(
        private readonly RouteReceiptForFiscalisationAction $route,
    ) {}

    public function handle(ReceiptPosted $event): void
    {
        $receipt = $event->receipt;

        if ($receipt->fiscalisation_status !== 'queued' || $receipt->fiscal_receipt_id !== null) {
            return;
        }

        $allocations = ReceiptAllocation::where('receipt_id', $receipt->id)->whereNotNull('component_id')->get();

        if ($allocations->isEmpty()) {
            return;
        }

        $componentCodes = FeeComponent::whereIn('id', $allocations->pluck('component_id')->unique())
            ->pluck('code', 'id');

        $lines = $allocations->map(fn (ReceiptAllocation $allocation): array => [
            'source_identifier' => (string) $componentCodes[$allocation->component_id],
            'description' => (string) $componentCodes[$allocation->component_id],
            'amount_minor' => (int) $allocation->amount_minor,
        ])->all();

        $fiscalReceipt = $this->route->execute(new RouteReceiptForFiscalisationData(
            schoolId: $receipt->school_id,
            sourceType: 'fee_component',
            sourceId: $receipt->id,
            receiptType: 'fiscal_invoice',
            currency: $receipt->currency,
            invoiceNumber: $receipt->receipt_number,
            receiptDate: $receipt->received_at,
            lines: $lines,
            paymentMethods: $receipt->tenders->pluck('tender_type')->unique()->values()->all(),
            performedByUserId: $receipt->received_by,
            buyerName: $receipt->payer_name,
        ));

        if ($fiscalReceipt !== null) {
            $receipt->update(['fiscal_receipt_id' => $fiscalReceipt->id]);
        }
    }
}
