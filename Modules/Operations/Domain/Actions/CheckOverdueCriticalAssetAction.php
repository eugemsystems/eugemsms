<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Operations\Domain\Events\CriticalAssetServiceOverdue;
use Modules\Operations\Models\MaintenanceAsset;

/**
 * ACT-CheckOverdueCriticalAsset (Book H2 OPS-02 §6 ⭐/BR-OPS-02-016/
 * AC-OPS-02-005). Only `criticality = 'critical'` assets alert the
 * head here — an overdue service on anything less critical is
 * ordinary maintenance-officer business, not this escalation.
 */
final class CheckOverdueCriticalAssetAction extends Action
{
    /**
     * @return Collection<int, MaintenanceAsset>
     */
    public function execute(int $schoolId): Collection
    {
        $overdue = MaintenanceAsset::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('criticality', 'critical')
            ->whereNotNull('next_service_due_on')
            ->where('next_service_due_on', '<', Carbon::now()->toDateString())
            ->get();

        foreach ($overdue as $asset) {
            event(new CriticalAssetServiceOverdue($asset));
        }

        return $overdue;
    }
}
