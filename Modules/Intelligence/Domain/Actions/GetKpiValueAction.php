<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\DataObjects\KpiValueResult;
use Modules\Intelligence\Domain\Registry\KpiRegistry;
use Modules\Intelligence\Models\KpiTarget;

/**
 * ACT-GetKpiValue (Book J INT-02 §3 ⭐/BR-INT-02-002/003
 * (AC-INT-02-001)).
 *
 * **Colour logic, read directly off the spec's own worked example**
 * ("collection rate is 87% against a 92% target with a 90% warning
 * threshold → renders red"): `warning_threshold_percent` is compared
 * DIRECTLY against the current value, on the same scale the KPI's own
 * `current`/`target` are expressed in — not as a percentage OF the
 * target (92 × 0.90 = 82.8 would make 87 amber, contradicting the
 * spec's own stated result). Green once the target is met or
 * exceeded (direction-aware via `higherIsBetter`); red once the
 * current value crosses below (or above, for a lower-is-better KPI)
 * the warning threshold; amber in between.
 */
final class GetKpiValueAction extends Action
{
    protected bool $transactional = false;

    public function execute(string $kpiKey, int $schoolId, int $academicYearId): KpiValueResult
    {
        $kpi = KpiRegistry::get($kpiKey)
            ?? throw new InvalidArgumentException("Unregistered KPI '{$kpiKey}'.");

        $currentValue = ($kpi->valueResolver)($schoolId);

        $override = KpiTarget::where('school_id', $schoolId)->where('kpi_key', $kpiKey)->where('academic_year_id', $academicYearId)->first();
        $targetValue = $override?->target_value ?? $kpi->defaultTargetValue;
        $warningThresholdPercent = $override?->warning_threshold_percent ?? 90.0;

        return new KpiValueResult(
            key: $kpi->key,
            label: $kpi->label,
            unit: $kpi->unit,
            currentValue: $currentValue,
            targetValue: $targetValue,
            warningThresholdPercent: $warningThresholdPercent,
            status: $this->status($currentValue, $targetValue, $warningThresholdPercent, $kpi->higherIsBetter),
            higherIsBetter: $kpi->higherIsBetter,
        );
    }

    private function status(float $current, ?float $target, float $warningThreshold, bool $higherIsBetter): string
    {
        if ($target === null) {
            return 'amber';
        }

        if ($higherIsBetter) {
            if ($current >= $target) {
                return 'green';
            }

            return $current < $warningThreshold ? 'red' : 'amber';
        }

        if ($current <= $target) {
            return 'green';
        }

        return $current > $warningThreshold ? 'red' : 'amber';
    }
}
