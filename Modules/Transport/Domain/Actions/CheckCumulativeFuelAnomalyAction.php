<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Transport\Domain\Events\FuelAnomalyDetected;
use Modules\Transport\Models\FuelLog;
use Modules\Transport\Models\Vehicle;

/**
 * ACT-CheckCumulativeFuelAnomaly (Book H2 OPS-01 §3 ⭐/BR-OPS-01-014/
 * AC-OPS-01-004). "A driver skimming five litres per fill passes
 * every individual variance test and shows up clearly in a rolling
 * thirty-day view" — this compares litres actually drawn over the
 * window against litres the vehicle's own `expected_km_per_litre`
 * implies it should have needed for the distance it actually covered,
 * catching a shortfall no single fill-up flags. Also escalates when
 * three or more unexplained anomalies have piled up on the same
 * vehicle within the window (BR-OPS-01-014's second sentence).
 */
final class CheckCumulativeFuelAnomalyAction extends Action
{
    private const int UNEXPLAINED_ESCALATION_THRESHOLD = 3;

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, FuelLog>
     */
    public function execute(int $schoolId): Collection
    {
        $scope = new ScopeChain(schoolId: $schoolId);
        $windowDays = (int) $this->settings->get('transport.fuel_rolling_window_days', $scope);
        $tolerancePercent = (float) $this->settings->get('transport.fuel_variance_tolerance_percent', $scope);
        $since = Carbon::now()->subDays($windowDays);

        $flagged = new Collection;

        $vehicles = Vehicle::where('school_id', $schoolId)->whereNotNull('expected_km_per_litre')->get();

        foreach ($vehicles as $vehicle) {
            $logs = FuelLog::where('vehicle_id', $vehicle->id)->where('fuelled_at', '>=', $since)->orderBy('fuelled_at')->get();

            if ($logs->count() < 2) {
                continue;
            }

            $actualLitres = (float) $logs->sum('litres');
            $kmDriven = (float) $logs->last()->odometer_km - (float) $logs->first()->odometer_km;
            $expectedKmPerLitre = (float) $vehicle->expected_km_per_litre;

            if ($kmDriven <= 0 || $expectedKmPerLitre <= 0) {
                continue;
            }

            $expectedLitres = $kmDriven / $expectedKmPerLitre;
            $variancePercent = (($actualLitres - $expectedLitres) / $expectedLitres) * 100;

            $latest = $logs->last();

            if ($variancePercent > $tolerancePercent && ! $latest->is_anomaly) {
                $this->transaction(function () use ($latest, $variancePercent): void {
                    $latest->update(['is_anomaly' => true]);
                    event(new FuelAnomalyDetected(
                        $latest,
                        sprintf('30-day cumulative consumption is %.1f%% above what expected efficiency implies for the distance covered.', $variancePercent),
                    ));
                });
                $flagged->push($latest);
            }

            $unexplainedCount = $logs->where('is_anomaly', true)->whereNull('anomaly_explanation')->count();

            if ($unexplainedCount >= self::UNEXPLAINED_ESCALATION_THRESHOLD) {
                event(new FuelAnomalyDetected(
                    $latest,
                    "{$unexplainedCount} unexplained anomalies on vehicle {$vehicle->fleet_number} in the last {$windowDays} days — escalated to the bursar.",
                ));
            }
        }

        return $flagged;
    }
}
