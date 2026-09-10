<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Utilities\Domain\Events\TokenReconciliationVariance;
use Modules\Utilities\Models\Meter;
use Modules\Utilities\Models\MeterReading;
use Modules\Utilities\Models\PrepaidTokenPurchase;

/**
 * ACT-CheckTokenReconciliation (Book H2 OPS-04 §3/BR-OPS-04-005/
 * AC-OPS-04-003). Nightly reconciliation's second half — credited
 * units against metered consumption, monthly, per meter.
 */
final class CheckTokenReconciliationAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, Meter>
     */
    public function execute(int $schoolId, CarbonInterface $periodStart, CarbonInterface $periodEnd): Collection
    {
        $tolerancePercent = (float) $this->settings->get('utilities.token_reconciliation_tolerance_percent', new ScopeChain(schoolId: $schoolId));
        $flagged = new Collection;

        $meters = Meter::where('school_id', $schoolId)->where('meter_type', 'electricity_prepaid')->get();

        foreach ($meters as $meter) {
            $creditedUnits = (float) PrepaidTokenPurchase::where('meter_id', $meter->id)
                ->where('credit_confirmed', true)
                ->whereBetween('credited_at', [$periodStart, $periodEnd])
                ->sum('units_purchased');

            $meteredConsumption = (float) MeterReading::where('meter_id', $meter->id)
                ->whereBetween('read_on', [$periodStart, $periodEnd])
                ->sum('consumption');

            if ($creditedUnits <= 0 && $meteredConsumption <= 0) {
                continue;
            }

            $base = max($creditedUnits, 0.0001);
            $variancePercent = (($meteredConsumption - $creditedUnits) / $base) * 100;

            if (abs($variancePercent) > $tolerancePercent) {
                event(new TokenReconciliationVariance($meter, $creditedUnits, $meteredConsumption, $variancePercent));
                $flagged->push($meter);
            }
        }

        return $flagged;
    }
}
