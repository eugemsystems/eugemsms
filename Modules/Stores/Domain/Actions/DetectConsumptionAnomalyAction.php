<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\Support\LiveOccupancyProvider;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\Events\ConsumptionAnomalyDetected;
use Modules\Stores\Models\ConsumptionAnomaly;
use Modules\Stores\Models\ConsumptionBaseline;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;

/**
 * ACT-DetectConsumptionAnomaly (Book H1 FIN-09 §7 ⭐/BR-FIN-09-022/023/
 * AC-FIN-09-008). Re-derives the period's own boarder-days from
 * `LiveOccupancyProvider` rather than reusing the baseline's average —
 * a week with a mid-term exeat has fewer boarder-days than the
 * baseline window did, and the anomaly check has to compare against
 * what actually happened, not the baseline's own occupancy. Returns
 * `null` when no baseline exists yet or the variance sits inside
 * tolerance — nothing is created in either case.
 */
final class DetectConsumptionAnomalyAction extends Action
{
    public function __construct(
        private readonly LiveOccupancyProvider $occupancy,
    ) {}

    public function execute(int $storeId, int $itemId, CarbonInterface $periodStart, CarbonInterface $periodEnd): ?ConsumptionAnomaly
    {
        $store = Store::findOrFail($storeId);
        $item = InventoryItem::findOrFail($itemId);

        $baseline = ConsumptionBaseline::where('store_id', $storeId)->where('item_id', $itemId)->first();

        if ($baseline === null) {
            return null;
        }

        $days = $periodStart->diffInDays($periodEnd) + 1;

        $actualQuantity = (float) StockMovement::query()
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->where('direction', 'out')
            ->whereBetween('occurred_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
            ->sum('quantity');

        $occupancyFactor = null;

        if ($store->store_type === 'kitchen') {
            $boarderDays = 0;

            for ($i = 0; $i < $days; $i++) {
                $boarderDays += $this->occupancy->liveOccupancy($periodStart->copy()->addDays($i), 'all')->present;
            }

            $occupancyFactor = $boarderDays;
            $expectedQuantity = (float) $baseline->expected_quantity * $boarderDays;
        } else {
            $expectedQuantity = (float) $baseline->expected_quantity * $days;
        }

        $variancePercent = $expectedQuantity > 0.0
            ? round(abs($actualQuantity - $expectedQuantity) / $expectedQuantity * 100, 2)
            : ($actualQuantity > 0.0 ? 100.0 : 0.0);

        if ($variancePercent <= (float) $baseline->tolerance_percent) {
            return null;
        }

        $severity = $item->is_high_risk || $variancePercent > (float) $baseline->tolerance_percent * 2 ? 'high' : 'medium';

        return $this->transaction(function () use ($store, $item, $periodStart, $periodEnd, $expectedQuantity, $actualQuantity, $variancePercent, $occupancyFactor, $severity): ConsumptionAnomaly {
            $anomaly = ConsumptionAnomaly::create([
                'school_id' => $store->school_id,
                'store_id' => $store->id,
                'item_id' => $item->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'expected_quantity' => $expectedQuantity,
                'actual_quantity' => $actualQuantity,
                'variance_percent' => $variancePercent,
                'occupancy_factor' => $occupancyFactor,
                'severity' => $severity,
                'status' => 'open',
                'detected_at' => Carbon::now(),
            ]);

            event(new ConsumptionAnomalyDetected($anomaly));

            return $anomaly;
        });
    }
}
