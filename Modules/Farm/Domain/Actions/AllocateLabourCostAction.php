<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Farm\Models\CropCycle;

/**
 * ACT-AllocateLabourCost (Book H2 OPS-03 §4/BR-OPS-03-003). Uses the
 * staff member's own known rate where the caller supplies one, else
 * `farm.casual_labour_daily_rate_minor` — the same "caller-supplied
 * rate, else a configured default" shape
 * `Modules\Operations\Domain\Actions\RecordWorkOrderLabourAction`
 * already uses, since `Modules\People` carries no per-staff daily
 * rate column here either.
 */
final class AllocateLabourCostAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $cropCycleId, float $days, ?int $dailyRateMinor = null): CropCycle
    {
        $cycle = CropCycle::findOrFail($cropCycleId);

        $rate = $dailyRateMinor
            ?? (int) $this->settings->get('farm.casual_labour_daily_rate_minor', new ScopeChain(schoolId: $cycle->school_id));
        $costMinor = (int) round($days * $rate);

        return $this->transaction(function () use ($cycle, $costMinor): CropCycle {
            $labourCostMinor = $cycle->labour_cost_minor + $costMinor;

            $cycle->update([
                'labour_cost_minor' => $labourCostMinor,
                'total_cost_minor' => $cycle->input_cost_minor + $labourCostMinor + $cycle->overhead_cost_minor,
            ]);

            return $cycle;
        });
    }
}
