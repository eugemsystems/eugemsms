<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\Events\ReorderLevelBreached;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreItemSetting;

/**
 * ACT-CheckReorderLevels (Book H1 FIN-09 §9/BR-FIN-09-024). Only ever
 * fires an event — a breach *suggests* a `FIN-08` purchase
 * requisition, it never raises one automatically.
 */
final class CheckReorderLevelsAction extends Action
{
    /**
     * @return Collection<int, StoreItemSetting>
     */
    public function execute(int $storeId): Collection
    {
        $store = Store::findOrFail($storeId);

        $breached = StoreItemSetting::query()
            ->where('store_id', $storeId)
            ->where('is_stocked', true)
            ->whereNotNull('reorder_level')
            ->get()
            ->filter(function (StoreItemSetting $setting) use ($store): bool {
                $onHand = $this->currentOnHand($store->id, $setting->item_id);

                return $onHand <= (float) $setting->reorder_level;
            });

        foreach ($breached as $setting) {
            $item = InventoryItem::findOrFail($setting->item_id);
            $onHand = $this->currentOnHand($store->id, $setting->item_id);

            event(new ReorderLevelBreached($store, $item, $onHand, (float) $setting->reorder_level));
        }

        return $breached;
    }

    private function currentOnHand(int $storeId, int $itemId): float
    {
        $ins = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->sum('quantity');
        $outs = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->sum('quantity');

        return $ins - $outs;
    }
}
