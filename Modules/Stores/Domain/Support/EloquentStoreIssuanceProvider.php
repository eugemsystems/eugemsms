<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Support;

use Modules\Boarding\Domain\Support\StoreIssuanceProvider;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * Book H1 FIN-09 §5 ⭐ — the real implementation of Book F BRD-04's
 * `StoreIssuanceProvider`, closing that interface for good.
 * `NullStoreIssuanceProvider` returned `null` from both methods
 * ("unavailable", never a number/boolean standing in for missing
 * data); this class only ever returns `null` when there is genuinely
 * no way to know — no kitchen store configured yet, or the item
 * hasn't been set up in `Stores` at all — never as a lazy default.
 * Resolves to the school's active `kitchen`-type store, since
 * `BRD-04` catering is what this interface exists for.
 */
final class EloquentStoreIssuanceProvider implements StoreIssuanceProvider
{
    public function __construct(
        private readonly StockCostingEngine $engine,
    ) {}

    public function currentCostMinor(int $schoolId, int $inventoryItemId): ?int
    {
        $store = $this->kitchenStore($schoolId);
        $item = InventoryItem::find($inventoryItemId);

        if ($store === null || $item === null) {
            return null;
        }

        return $this->engine->weightedAverageUnitCostMinor($store, $item);
    }

    public function checkAvailability(int $schoolId, int $inventoryItemId, float $requiredQuantity): ?bool
    {
        $store = $this->kitchenStore($schoolId);
        $item = InventoryItem::find($inventoryItemId);

        if ($store === null || $item === null) {
            return null;
        }

        $onHand = (float) $this->engine->availableLots($store, $item)->sum('quantity_remaining');

        return $onHand >= $requiredQuantity;
    }

    private function kitchenStore(int $schoolId): ?Store
    {
        return Store::query()
            ->where('school_id', $schoolId)
            ->where('store_type', 'kitchen')
            ->where('is_active', true)
            ->first();
    }
}
