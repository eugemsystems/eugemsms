<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockLot;

/**
 * Book F BRD-04 §4 — the real `StoreIssuanceProvider`, now that FIN-09 exists. Cost is the item's
 * standard cost when it has one, otherwise the unit cost of the most recent lot received in the
 * school's base currency; availability is the quantity left in lots, summed across the school's
 * stores. Both stay `null` when there is nothing to base an answer on (no such item, no priced lot,
 * or no stock record at all for availability) — an uncosted ingredient is never shown as free, and an
 * unknown stock position is never shown as "none".
 */
final class StoresIssuanceProvider implements StoreIssuanceProvider
{
    public function currentCostMinor(int $schoolId, int $inventoryItemId): ?int
    {
        $currency = School::query()->whereKey($schoolId)->value('base_currency');
        $item = InventoryItem::withoutGlobalScopes()->where('school_id', $schoolId)->find($inventoryItemId);

        if ($item === null) {
            return null;
        }

        if ($item->standard_cost_minor !== null && ($item->standard_cost_currency === null || $item->standard_cost_currency === $currency)) {
            return $item->standard_cost_minor;
        }

        $lot = StockLot::withoutGlobalScopes()->where('school_id', $schoolId)->where('item_id', $inventoryItemId)->where('currency', $currency)
            ->orderByDesc('received_on')->orderByDesc('id')->first();

        return $lot !== null ? $lot->unit_cost_minor : null;
    }

    public function checkAvailability(int $schoolId, int $inventoryItemId, float $requiredQuantity): ?bool
    {
        $lots = StockLot::withoutGlobalScopes()->where('school_id', $schoolId)->where('item_id', $inventoryItemId);

        if (! (clone $lots)->exists()) {
            return null;
        }

        return (float) $lots->where('is_depleted', false)->sum('quantity_remaining') >= $requiredQuantity;
    }
}
