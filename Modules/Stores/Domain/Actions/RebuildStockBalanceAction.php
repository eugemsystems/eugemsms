<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\StockBalance;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\StockMovement;

/**
 * ACT-RebuildStockBalance (Book H1 FIN-09 §2/BR-FIN-09-002). The
 * nightly verifier — `stock_balances` is a cache, and this is the
 * only path that writes to it. Rebuilds fresh from `stock_movements`
 * and `stock_lots` every time, the same "recompute from source, never
 * trust the cache" discipline used throughout this codebase.
 */
final class RebuildStockBalanceAction extends Action
{
    public function execute(int $schoolId, int $storeId, int $itemId): StockBalance
    {
        $ins = (float) StockMovement::where('school_id', $schoolId)->where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->sum('quantity');
        $outs = (float) StockMovement::where('school_id', $schoolId)->where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->sum('quantity');
        $onHand = $ins - $outs;

        $lastMovement = StockMovement::where('school_id', $schoolId)->where('store_id', $storeId)->where('item_id', $itemId)->orderByDesc('id')->first();
        $lastReceipt = StockMovement::where('school_id', $schoolId)->where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->orderByDesc('occurred_at')->first();
        $lastIssue = StockMovement::where('school_id', $schoolId)->where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->orderByDesc('occurred_at')->first();

        $remainingLots = StockLot::where('school_id', $schoolId)->where('store_id', $storeId)->where('item_id', $itemId)->where('is_depleted', false)->get();
        $valueMinor = (int) $remainingLots->sum(fn (StockLot $lot): float => (float) $lot->quantity_remaining * $lot->base_unit_cost_minor);
        $averageUnitCostMinor = $onHand > 0 ? (int) round($valueMinor / $onHand) : null;

        return $this->transaction(fn (): StockBalance => StockBalance::updateOrCreate(
            ['school_id' => $schoolId, 'store_id' => $storeId, 'item_id' => $itemId],
            [
                'quantity_on_hand' => $onHand,
                'quantity_committed' => 0,
                'quantity_available' => $onHand,
                'value_minor' => $valueMinor,
                'currency' => $lastMovement->currency ?? 'USD',
                'average_unit_cost_minor' => $averageUnitCostMinor,
                'last_movement_id' => $lastMovement?->id,
                'last_received_on' => $lastReceipt?->occurred_at->toDateString(),
                'last_issued_on' => $lastIssue?->occurred_at->toDateString(),
                'rebuilt_at' => Carbon::now(),
            ],
        ));
    }
}
