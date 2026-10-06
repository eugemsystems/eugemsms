<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Listeners;

use Modules\Stores\Domain\Actions\CapitalizeAssetAction;
use Modules\Stores\Domain\DataObjects\CapitalizeAssetData;
use Modules\Stores\Domain\Events\ItemCapitalisationDue;
use Modules\Stores\Domain\Support\CapitalisationUnits;

/**
 * Book H1 FIN-10 BR-FIN-10-002 — the real consumer of FIN-09's
 * `ItemCapitalisationDue`. The issue expensed the item to the requesting cost
 * centre; capitalising reclassifies that expense (Dr asset, Cr the expense
 * account the issue used). A no-op when the item names no asset category.
 */
final class CapitaliseAssetOnItemCapitalisationDueListener
{
    public function __construct(
        private readonly CapitalizeAssetAction $capitalize,
    ) {}

    public function handle(ItemCapitalisationDue $event): void
    {
        $item = $event->item;
        $movement = $event->movement;

        if ($item->asset_category_id === null || $movement->expense_account_id === null || $movement->cost_centre_id === null) {
            return;
        }

        $costs = CapitalisationUnits::costs((float) $movement->quantity, $event->unitCostMinor);

        foreach ($costs as $index => $costMinor) {
            $this->capitalize->execute(new CapitalizeAssetData(
                schoolId: $movement->school_id,
                academicYearId: $movement->academic_year_id,
                termId: $movement->term_id,
                categoryId: $item->asset_category_id,
                name: count($costs) > 1 ? $item->name.' ('.($index + 1).'/'.count($costs).')' : $item->name,
                acquisitionDate: $movement->occurred_at,
                acquisitionCostMinor: $costMinor,
                currency: $movement->currency,
                acquisitionSource: 'stock_issue',
                costCentreId: $movement->cost_centre_id,
                contraAccountId: $movement->expense_account_id,
                performedByUserId: (int) $movement->performed_by,
                stockMovementId: $movement->id,
            ));
        }
    }
}
