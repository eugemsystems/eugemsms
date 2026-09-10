<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\ConsumptionAnomaly;

/**
 * ACT-GetCommodityAnomalyDashboard (Book J INT-03 §2/BR-INT-03-011
 * (AC-INT-03-005)). Reads `FIN-09`'s own `ConsumptionAnomaly` records
 * directly — no duplicate anomaly-detection logic exists in this
 * module.
 *
 * **Honest scope boundary.** `OPS-01`'s own fuel anomaly check
 * (`Modules\Transport\Domain\Actions\CheckCumulativeFuelAnomalyAction`)
 * dispatches an event but has no persisted, queryable anomaly table in
 * this codebase yet — there is nothing real to surface for it. Rather
 * than fabricate a row, this pass returns an empty fuel-anomaly list
 * and states the gap here instead of hiding it.
 */
final class GetCommodityAnomalyDashboardAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{consumption_anomalies: array<int, ConsumptionAnomaly>, fuel_anomalies: array<int, mixed>}
     */
    public function execute(int $schoolId): array
    {
        return [
            'consumption_anomalies' => ConsumptionAnomaly::where('school_id', $schoolId)->orderByDesc('detected_at')->get()->all(),
            'fuel_anomalies' => [],
        ];
    }
}
