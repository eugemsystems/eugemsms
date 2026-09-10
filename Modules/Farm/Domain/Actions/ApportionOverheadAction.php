<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Farm\Models\CropCycle;

/**
 * ACT-ApportionOverhead (Book H2 OPS-03 §4/BR-OPS-03-004). Basis is
 * `farm.overhead_allocation_basis`: `hectares` apportions a pool
 * pro-rata by the cycle's own planted area against the pool's total
 * hectares; `fixed_rate` applies the pool amount directly as a flat
 * per-cycle charge. Computing the pool itself (aggregate farm
 * overhead for a period) is the caller's own job — this action only
 * apportions a given pool to a given cycle.
 */
final class ApportionOverheadAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $cropCycleId, int $overheadPoolMinor, ?float $totalHectaresInPool = null): CropCycle
    {
        $cycle = CropCycle::findOrFail($cropCycleId);
        $basis = $this->settings->get('farm.overhead_allocation_basis', new ScopeChain(schoolId: $cycle->school_id));

        $costMinor = $basis === 'hectares' && $totalHectaresInPool !== null && $totalHectaresInPool > 0
            ? (int) round($overheadPoolMinor * ((float) $cycle->area_planted_hectares / $totalHectaresInPool))
            : $overheadPoolMinor;

        return $this->transaction(function () use ($cycle, $costMinor): CropCycle {
            $overheadCostMinor = $cycle->overhead_cost_minor + $costMinor;

            $cycle->update([
                'overhead_cost_minor' => $overheadCostMinor,
                'total_cost_minor' => $cycle->input_cost_minor + $cycle->labour_cost_minor + $overheadCostMinor,
            ]);

            return $cycle;
        });
    }
}
