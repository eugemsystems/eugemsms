<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Support;

use Modules\Stores\Models\FixedAsset;

/**
 * Book H1 FIN-10 §3/BR-FIN-10-006/AC-FIN-10-001. A pure calculator —
 * it reads an asset's own state and returns a charge, mutating
 * nothing (the same "engine computes, action persists" split as
 * `FIN-09`'s `StockCostingEngine`). NBV never falls below residual in
 * any method, and the charge is trimmed so the LAST period lands
 * exactly on residual rather than one cent short or over from
 * accumulated rounding.
 */
final class DepreciationCalculator
{
    public function monthlyCharge(FixedAsset $asset, float $unitsConsumedThisPeriod = 0.0): int
    {
        if (! $asset->is_depreciable || $asset->fully_depreciated) {
            return 0;
        }

        $openingNbv = $asset->net_book_value_minor;
        $residual = $asset->residual_value_minor;
        $maxCharge = max(0, $openingNbv - $residual);

        if ($maxCharge <= 0) {
            return 0;
        }

        $raw = match ($asset->depreciation_method) {
            'straight_line' => $this->straightLine($asset),
            'reducing_balance' => $this->reducingBalance($asset),
            'units_of_production' => $this->unitsOfProduction($asset, $unitsConsumedThisPeriod),
            default => 0,
        };

        return min($raw, $maxCharge);
    }

    private function straightLine(FixedAsset $asset): int
    {
        $usefulLifeYears = (float) $asset->useful_life_years;

        if ($usefulLifeYears <= 0) {
            return 0;
        }

        $totalDepreciable = $asset->acquisition_cost_minor - $asset->residual_value_minor;

        return (int) round($totalDepreciable / ($usefulLifeYears * 12));
    }

    private function reducingBalance(FixedAsset $asset): int
    {
        $usefulLifeYears = (float) $asset->useful_life_years;
        $cost = $asset->acquisition_cost_minor;

        if ($usefulLifeYears <= 0 || $cost <= 0 || $asset->residual_value_minor <= 0) {
            // A zero residual makes the reducing-balance rate formula
            // undefined (division toward a zero base) — such an asset
            // must use straight-line or units-of-production instead.
            return 0;
        }

        $annualRate = 1 - ($asset->residual_value_minor / $cost) ** (1 / $usefulLifeYears);

        return (int) round($asset->net_book_value_minor * ($annualRate / 12));
    }

    private function unitsOfProduction(FixedAsset $asset, float $unitsConsumedThisPeriod): int
    {
        $totalUnitsExpected = (float) $asset->total_units_expected;

        if ($totalUnitsExpected <= 0) {
            return 0;
        }

        $perUnit = ($asset->acquisition_cost_minor - $asset->residual_value_minor) / $totalUnitsExpected;

        return (int) round($perUnit * $unitsConsumedThisPeriod);
    }
}
