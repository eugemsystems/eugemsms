<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\Support\LiveOccupancyProvider;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\ConsumptionBaseline;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;

/**
 * ACT-ComputeConsumptionBaseline (Book H1 FIN-09 §7 ⭐/BR-FIN-09-022).
 * A boarding-serving store (`store_type = 'kitchen'`) normalises its
 * baseline **per boarder-day** — the average of `LiveOccupancyProvider`'s
 * own `present` count across the window, read the same way `BRD-04`
 * catering reads it, never a re-derivation from `bed_allocations`. A
 * non-boarding store baselines on a flat daily average instead.
 */
final class ComputeConsumptionBaselineAction extends Action
{
    public function __construct(
        private readonly LiveOccupancyProvider $occupancy,
    ) {}

    public function execute(int $storeId, int $itemId, string $periodType, int $computedFromDays, float $toleranceOverridePercent = 15.0): ConsumptionBaseline
    {
        $store = Store::findOrFail($storeId);
        $since = Carbon::now()->subDays($computedFromDays);

        $totalConsumed = (float) StockMovement::query()
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->where('direction', 'out')
            ->where('occurred_at', '>=', $since)
            ->sum('quantity');

        if ($store->store_type === 'kitchen') {
            $boarderDays = 0;

            for ($i = 0; $i < $computedFromDays; $i++) {
                $day = $since->copy()->addDays($i);
                $boarderDays += $this->occupancy->liveOccupancy($day, 'all')->present;
            }

            $expectedQuantity = $boarderDays > 0 ? $totalConsumed / $boarderDays : 0.0;
        } else {
            $expectedQuantity = $computedFromDays > 0 ? $totalConsumed / $computedFromDays : 0.0;
        }

        return $this->transaction(fn (): ConsumptionBaseline => ConsumptionBaseline::updateOrCreate(
            ['store_id' => $storeId, 'item_id' => $itemId, 'period_type' => $periodType],
            [
                'school_id' => $store->school_id,
                'expected_quantity' => $expectedQuantity,
                'tolerance_percent' => $toleranceOverridePercent,
                'computed_from_days' => $computedFromDays,
                'last_computed_at' => Carbon::now(),
            ],
        ));
    }
}
