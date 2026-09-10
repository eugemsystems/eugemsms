<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Stores\Domain\DataObjects\StockConsumptionResult;
use Modules\Stores\Domain\DataObjects\StockLotConsumption;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\Store;

/**
 * Book H1 FIN-09 §4 ⭐/BR-FIN-09-004/009/010/011. A deliberately
 * focused engine: it consumes lots and mutates their
 * `quantity_remaining` under a row lock (so two concurrent issues can
 * never consume the same layer, AC-FIN-09-004), and returns what it
 * took — it does not create `stock_movements` rows or post journals
 * itself, matching `BedAllocationEngine`'s own "engine computes and
 * mutates the narrow thing it owns, the Action persists everything
 * else" split.
 *
 * FIFO consumes by `received_on` then `id` (BR-FIN-09-004); a
 * perishable item consumes by earliest `expiry_date` first where that
 * differs from receipt order (BR-FIN-09-010). Expired lots are never
 * offered (BR-FIN-09-011) — they are excluded from consideration
 * entirely, not merely deprioritised.
 */
final class StockCostingEngine
{
    public function consume(Store $store, InventoryItem $item, float $quantity): StockConsumptionResult
    {
        $query = StockLot::query()
            ->where('store_id', $store->id)
            ->where('item_id', $item->id)
            ->where('is_depleted', false)
            ->where('quantity_remaining', '>', 0)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now()->toDateString()));

        if ($item->is_perishable) {
            $query->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date');
        }

        $lots = $query->orderBy('received_on')->orderBy('id')->lockForUpdate()->get();

        $remaining = $quantity;
        $consumptions = collect();

        foreach ($lots as $lot) {
            if ($remaining <= 0.0) {
                break;
            }

            $take = min($remaining, (float) $lot->quantity_remaining);

            if ($take <= 0.0) {
                continue;
            }

            $costMinor = (int) round($take * $lot->base_unit_cost_minor);

            $consumptions->push(new StockLotConsumption($lot, $take, $costMinor));

            $newRemaining = (float) $lot->quantity_remaining - $take;
            $lot->update([
                'quantity_remaining' => $newRemaining,
                'is_depleted' => $newRemaining <= 0.0,
            ]);

            $remaining -= $take;
        }

        return new StockConsumptionResult($consumptions, max(0.0, $remaining));
    }

    /**
     * BR-FIN-09-005 — the cost basis for a negative-stock issue: the
     * most recent lot ever received for this store+item (depleted or
     * not), falling back to the item's own standard cost.
     */
    public function lastKnownUnitCostMinor(Store $store, InventoryItem $item): int
    {
        $lastLot = StockLot::query()
            ->where('store_id', $store->id)
            ->where('item_id', $item->id)
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->first();

        if ($lastLot !== null) {
            return (int) $lastLot->base_unit_cost_minor;
        }

        return (int) ($item->standard_cost_minor ?? 0);
    }

    /**
     * Weighted average of remaining, unexpired lots — for planning
     * (`StoreIssuanceProvider::currentCostMinor()`), never for posting.
     */
    public function weightedAverageUnitCostMinor(Store $store, InventoryItem $item): ?int
    {
        $lots = StockLot::query()
            ->where('store_id', $store->id)
            ->where('item_id', $item->id)
            ->where('is_depleted', false)
            ->where('quantity_remaining', '>', 0)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now()->toDateString()))
            ->get();

        if ($lots->isEmpty()) {
            return null;
        }

        $totalQuantity = (float) $lots->sum('quantity_remaining');

        if ($totalQuantity <= 0.0) {
            return null;
        }

        $totalValue = $lots->sum(fn (StockLot $lot): float => (float) $lot->quantity_remaining * $lot->base_unit_cost_minor);

        return (int) round($totalValue / $totalQuantity);
    }

    /**
     * @return Collection<int, StockLot>
     */
    public function availableLots(Store $store, InventoryItem $item): Collection
    {
        return StockLot::query()
            ->where('store_id', $store->id)
            ->where('item_id', $item->id)
            ->where('is_depleted', false)
            ->where('quantity_remaining', '>', 0)
            ->get();
    }
}
