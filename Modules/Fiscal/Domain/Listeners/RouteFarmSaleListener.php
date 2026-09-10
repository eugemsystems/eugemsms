<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Listeners;

use Modules\Farm\Domain\Events\FarmSaleRecorded;
use Modules\Fiscal\Domain\Actions\RouteReceiptForFiscalisationAction;
use Modules\Fiscal\Domain\DataObjects\RouteReceiptForFiscalisationData;

/**
 * Book H3 FIN-13 §9, closing the retro-fit `Modules\Farm`'s own
 * `farm_sales` migration and `RecordFarmSaleAction` docblocks name
 * explicitly: `fiscal_receipt_id` was left null because `FIN-13`
 * didn't exist. A real, additive listener on the already-dispatched
 * `FarmSaleRecorded` event — not an edit to `RecordFarmSaleAction`
 * itself. A farm sale is one undivided commercial line — no per-line
 * component code exists to match on, so it routes against a
 * `source_type = 'farm_sale'` rule with no `source_identifier`.
 */
final class RouteFarmSaleListener
{
    public function __construct(
        private readonly RouteReceiptForFiscalisationAction $route,
    ) {}

    public function handle(FarmSaleRecorded $event): void
    {
        $sale = $event->sale;

        if ($sale->fiscal_receipt_id !== null) {
            return;
        }

        $fiscalReceipt = $this->route->execute(new RouteReceiptForFiscalisationData(
            schoolId: $sale->school_id,
            sourceType: 'farm_sale',
            sourceId: $sale->id,
            receiptType: 'fiscal_invoice',
            currency: $sale->currency,
            invoiceNumber: $sale->sale_number,
            receiptDate: $sale->sale_date,
            lines: [[
                'source_identifier' => 'farm_sale',
                'description' => $sale->item_description,
                'amount_minor' => $sale->total_minor,
            ]],
            paymentMethods: ['cash'],
            performedByUserId: $event->performedByUserId,
            buyerName: $sale->buyer_name,
        ));

        if ($fiscalReceipt !== null) {
            $sale->update(['fiscal_receipt_id' => $fiscalReceipt->id]);
        }
    }
}
