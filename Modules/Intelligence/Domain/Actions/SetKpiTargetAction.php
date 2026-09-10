<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\KpiTarget;

/**
 * ACT-SetKpiTarget (Book J INT-02 §2/BR-INT-02-002). A school may
 * override any target for its own year — `GetKpiValueAction` falls
 * back to the KPI's own system default whenever no override exists.
 */
final class SetKpiTargetAction extends Action
{
    public function execute(int $schoolId, string $kpiKey, int $academicYearId, float $targetValue, float $warningThresholdPercent = 90.0): KpiTarget
    {
        return $this->transaction(fn (): KpiTarget => KpiTarget::updateOrCreate(
            ['school_id' => $schoolId, 'kpi_key' => $kpiKey, 'academic_year_id' => $academicYearId],
            ['target_value' => $targetValue, 'warning_threshold_percent' => $warningThresholdPercent],
        ));
    }
}
