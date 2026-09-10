<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Stores\Models\StockTakeLine;

/**
 * ACT-SubmitStockCount (Book H1 FIN-09 §7/BR-FIN-09-015/AC-FIN-09-006).
 * `system_quantity` is only ever compared here, on the server, once
 * the count is submitted — never sent to the counter beforehand.
 */
final class SubmitStockCountAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $stockTakeLineId, float $countedQuantity, int $countedByUserId, ?int $unitCostMinor = null): StockTakeLine
    {
        $line = StockTakeLine::findOrFail($stockTakeLineId);
        $scope = new ScopeChain(schoolId: $line->school_id);
        $recountThreshold = (float) $this->settings->get('inventory.stocktake_recount_variance_percent', $scope);

        $variance = $countedQuantity - (float) $line->system_quantity;
        $variancePercent = (float) $line->system_quantity > 0
            ? round(abs($variance) / (float) $line->system_quantity * 100, 2)
            : ($variance !== 0.0 ? 100.0 : 0.0);

        return $this->transaction(fn (): StockTakeLine => tap($line)->update([
            'counted_quantity' => $countedQuantity,
            'variance_quantity' => $variance,
            'variance_percent' => $variancePercent,
            'variance_value_minor' => $unitCostMinor !== null ? (int) round($variance * $unitCostMinor) : null,
            'requires_recount' => $variancePercent > $recountThreshold,
            'counted_by' => $countedByUserId,
            'counted_at' => Carbon::now(),
        ]));
    }
}
