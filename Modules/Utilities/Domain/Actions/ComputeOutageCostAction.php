<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\School;
use Modules\Operations\Models\WorkOrder;
use Modules\Stores\Models\DepreciationEntry;
use Modules\Utilities\Domain\DataObjects\OutageCostResult;
use Modules\Utilities\Models\Generator;
use Modules\Utilities\Models\GeneratorRun;
use Modules\Utilities\Models\Meter;
use Modules\Utilities\Models\PrepaidTokenPurchase;

/**
 * ACT-ComputeOutageCost (Book H2 OPS-04 §4 ⭐/AC-OPS-04-006). Real
 * `generator_cost` includes diesel actually drawn plus real `OPS-02`
 * maintenance cost and real `FIN-10` depreciation on the generator's
 * own linked records — the same aggregation
 * `Modules\Transport\Domain\Actions\ComputeRouteCostingAction`
 * already uses for a route. See `OutageCostResult`'s own docblock for
 * what's deliberately left out.
 */
final class ComputeOutageCostAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $schoolId, CarbonInterface $periodStart, CarbonInterface $periodEnd): OutageCostResult
    {
        $loadFactor = (float) $this->settings->get('utilities.generator_load_factor', new ScopeChain(schoolId: $schoolId));

        $gridKwh = (float) Meter::where('meters.school_id', $schoolId)
            ->join('meter_readings', 'meter_readings.meter_id', '=', 'meters.id')
            ->whereBetween('meter_readings.read_on', [$periodStart, $periodEnd])
            ->sum('meter_readings.consumption');

        $gridCostMinor = (int) PrepaidTokenPurchase::where('school_id', $schoolId)
            ->where('credit_confirmed', true)
            ->whereBetween('credited_at', [$periodStart, $periodEnd])
            ->sum('amount_paid_minor');

        $generators = Generator::where('school_id', $schoolId)->get();
        $generatorKwh = 0.0;
        $generatorCostMinor = 0;
        $outageHours = 0.0;

        foreach ($generators as $generator) {
            $runs = GeneratorRun::where('generator_id', $generator->id)
                ->whereBetween('run_date', [$periodStart, $periodEnd])
                ->get();

            $hours = (float) $runs->sum('hours_run');
            $generatorKwh += $hours * (float) $generator->capacity_kva * $loadFactor;
            $generatorCostMinor += (int) $runs->sum('diesel_cost_minor');
            $outageHours += (float) $runs->where('reason', 'load_shedding')->sum('hours_run');

            if ($generator->maintenance_asset_id !== null) {
                $generatorCostMinor += (int) WorkOrder::where('maintenance_asset_id', $generator->maintenance_asset_id)
                    ->whereBetween('completed_at', [$periodStart, $periodEnd])
                    ->sum('total_cost_minor');
            }

            if ($generator->fixed_asset_id !== null) {
                $generatorCostMinor += (int) DepreciationEntry::whereHas('run', fn ($query) => $query->whereBetween('run_date', [$periodStart, $periodEnd]))
                    ->where('asset_id', $generator->fixed_asset_id)
                    ->sum('depreciation_minor');
            }
        }

        return new OutageCostResult(
            gridKwh: $gridKwh,
            gridCostMinor: $gridCostMinor,
            generatorKwh: $generatorKwh,
            generatorCostMinor: $generatorCostMinor,
            outageHours: $outageHours,
            currency: School::findOrFail($schoolId)->base_currency,
        );
    }
}
