<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Listeners;

use Modules\Stores\Domain\Actions\CapitalizeAssetAction;
use Modules\Stores\Domain\DataObjects\CapitalizeAssetData;
use Modules\Stores\Domain\Events\CapitalPurchaseReceived;
use Modules\Stores\Domain\Support\CapitalisationUnits;

/**
 * Book H1 FIN-10 BR-FIN-10-002 — the real consumer of FIN-08's
 * `CapitalPurchaseReceived`. The goods receipt debited the line's expense
 * account; capitalising reclassifies it (Dr asset, Cr that expense account).
 * A no-op when the PO line names no asset category, so a school that has not
 * set one up keeps capitalising by hand.
 */
final class CapitaliseAssetOnCapitalPurchaseReceivedListener
{
    public function __construct(
        private readonly CapitalizeAssetAction $capitalize,
    ) {}

    public function handle(CapitalPurchaseReceived $event): void
    {
        $grnLine = $event->grnLine;
        $poLine = $grnLine->poLine;

        if ($poLine->asset_category_id === null || $poLine->expense_account_id === null) {
            return;
        }

        $grn = $grnLine->grn;
        $order = $poLine->purchaseOrder;
        $costs = CapitalisationUnits::costs((float) $grnLine->quantity_accepted, $event->unitCostMinor);

        foreach ($costs as $index => $costMinor) {
            $this->capitalize->execute(new CapitalizeAssetData(
                schoolId: $grn->school_id,
                academicYearId: $order->academic_year_id,
                termId: $grn->term_id,
                categoryId: $poLine->asset_category_id,
                name: count($costs) > 1 ? $poLine->description.' ('.($index + 1).'/'.count($costs).')' : $poLine->description,
                acquisitionDate: $grn->received_on,
                acquisitionCostMinor: $costMinor,
                currency: $order->currency,
                acquisitionSource: 'purchase',
                costCentreId: $order->cost_centre_id,
                contraAccountId: $poLine->expense_account_id,
                performedByUserId: (int) $grn->received_by,
                supplierId: $grn->supplier_id,
                purchaseOrderId: $order->id,
                grnId: $grn->id,
            ));
        }
    }
}
