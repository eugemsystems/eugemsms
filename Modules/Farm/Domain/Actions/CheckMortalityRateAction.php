<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Farm\Domain\Events\MortalityRateExceeded;
use Modules\Farm\Models\Livestock;
use Modules\Farm\Models\LivestockEvent;
use Modules\Farm\Models\ProductionUnit;

/**
 * ACT-CheckMortalityRate (Book H2 OPS-03 §4/BR-OPS-03-014/
 * AC-OPS-03-007). Deaths in the window against the population still
 * active plus those deaths — the denominator a rate needs to mean
 * anything.
 */
final class CheckMortalityRateAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $productionUnitId, CarbonInterface $periodStart, CarbonInterface $periodEnd): ?float
    {
        $unit = ProductionUnit::findOrFail($productionUnitId);
        $livestockIds = Livestock::where('production_unit_id', $unit->id)->pluck('id');

        $deaths = (int) LivestockEvent::whereIn('livestock_id', $livestockIds)
            ->where('event_type', 'death')
            ->whereBetween('event_date', [$periodStart, $periodEnd])
            ->sum('head_count_affected');

        if ($deaths === 0) {
            return null;
        }

        $activeHeadCount = (int) Livestock::where('production_unit_id', $unit->id)
            ->where('status', 'active')
            ->sum('head_count');

        $population = $activeHeadCount + $deaths;

        if ($population <= 0) {
            return null;
        }

        $mortalityPercent = ($deaths / $population) * 100;
        $alertPercent = (float) $this->settings->get('farm.livestock_mortality_alert_percent', new ScopeChain(schoolId: $unit->school_id));

        if ($mortalityPercent > $alertPercent) {
            event(new MortalityRateExceeded($unit, $mortalityPercent));
        }

        return $mortalityPercent;
    }
}
