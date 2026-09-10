<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Stores\Domain\Actions\ApproveStoreRequisitionAction;
use Modules\Stores\Domain\Actions\IssueStockAction;
use Modules\Stores\Domain\Actions\RequestStoreRequisitionAction;
use Modules\Stores\Domain\DataObjects\RequestStoreRequisitionData;
use Modules\Transport\Domain\DataObjects\RecordFuelLogData;
use Modules\Transport\Domain\Events\FuelAnomalyDetected;
use Modules\Transport\Models\FuelLog;
use Modules\Transport\Models\Trip;
use Modules\Transport\Models\Vehicle;

/**
 * ACT-RecordFuelLog (Book H2 OPS-01 §3/§4 ⭐/BR-OPS-01-013/014/
 * AC-OPS-01-003). A school-tank draw is a real `FIN-09` requisition
 * against the vehicle's own cost centre — the same "one path for cost
 * and stock" pattern `OPS-02`'s own `IssuePartsToWorkOrderAction`
 * uses. Every individual anomaly test from §3 runs here except the
 * 30-day rolling check, which needs a window of entries and lives in
 * `CheckCumulativeFuelAnomalyAction` instead.
 */
final class RecordFuelLogAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly RequestStoreRequisitionAction $requestRequisition,
        private readonly ApproveStoreRequisitionAction $approveRequisition,
        private readonly IssueStockAction $issueStock,
    ) {}

    public function execute(RecordFuelLogData $data): FuelLog
    {
        $vehicle = Vehicle::findOrFail($data->vehicleId);
        $scope = new ScopeChain(schoolId: $data->schoolId);
        $tolerancePercent = (float) $this->settings->get('transport.fuel_variance_tolerance_percent', $scope);
        $minRefuelHours = (int) $this->settings->get('transport.min_refuel_hours', $scope);

        $priorLog = FuelLog::where('vehicle_id', $vehicle->id)->orderByDesc('fuelled_at')->first();

        $reasons = [];

        if ($priorLog !== null && $data->odometerKm < (float) $priorLog->odometer_km) {
            $reasons[] = 'Odometer reading is lower than the previous fuel log entry.';
        }

        if ($vehicle->tank_capacity_litres !== null && $data->litres > (float) $vehicle->tank_capacity_litres) {
            $reasons[] = "Litres exceed the vehicle's tank capacity.";
        }

        if ($priorLog !== null && $data->fuelledAt->diffInHours($priorLog->fuelled_at, true) < $minRefuelHours) {
            $reasons[] = "Refuelled within {$minRefuelHours} hours of the previous fill.";
        }

        $hasTrip = Trip::where('vehicle_id', $vehicle->id)->whereDate('trip_date', $data->fuelledAt->toDateString())->exists();

        if (! $hasTrip) {
            $reasons[] = 'No recorded trip on the fuelling date.';
        }

        $kmSinceLast = null;
        $kmPerLitre = null;
        $variancePercent = null;

        if ($priorLog !== null && $data->odometerKm >= (float) $priorLog->odometer_km) {
            $kmSinceLast = $data->odometerKm - (float) $priorLog->odometer_km;
            $kmPerLitre = $data->litres > 0 ? $kmSinceLast / $data->litres : 0.0;

            if ($vehicle->expected_km_per_litre !== null && (float) $vehicle->expected_km_per_litre > 0) {
                $variancePercent = (($kmPerLitre - (float) $vehicle->expected_km_per_litre) / (float) $vehicle->expected_km_per_litre) * 100;

                if (abs($variancePercent) > $tolerancePercent) {
                    $reasons[] = sprintf('Fuel efficiency variance of %.1f%% exceeds the %d%% tolerance.', $variancePercent, (int) $tolerancePercent);
                }
            }
        }

        $isAnomaly = $reasons !== [];
        $totalCostMinor = (int) round($data->litres * $data->unitPriceMinor);

        return $this->transaction(function () use ($data, $vehicle, $kmSinceLast, $kmPerLitre, $variancePercent, $isAnomaly, $reasons, $totalCostMinor): FuelLog {
            $requisitionId = null;
            $journalId = null;

            if ($data->source === 'school_tank') {
                if ($data->storeId === null || $data->itemId === null) {
                    throw ValidationException::withMessages([
                        'storeId' => 'A store and item are required for a school-tank draw (BR-OPS-01-013).',
                    ]);
                }

                $requisition = $this->requestRequisition->execute(new RequestStoreRequisitionData(
                    schoolId: $data->schoolId,
                    academicYearId: $data->academicYearId,
                    termId: $data->termId,
                    storeId: $data->storeId,
                    costCentreId: $vehicle->cost_centre_id,
                    purpose: "Fuel — {$vehicle->fleet_number}",
                    requestedByUserId: $data->authorisedByUserId,
                    currency: $data->currency,
                    lines: [['itemId' => $data->itemId, 'quantity' => $data->litres, 'unit' => 'litre']],
                ));
                $this->approveRequisition->execute($requisition->id, $data->authorisedByUserId);
                $issued = $this->issueStock->execute($requisition->id, $data->authorisedByUserId);
                $requisitionId = $issued->id;
                $journalId = $issued->journal_id;
            }

            $fuelLog = FuelLog::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'vehicle_id' => $data->vehicleId,
                'fuelled_at' => $data->fuelledAt,
                'odometer_km' => $data->odometerKm,
                'litres' => $data->litres,
                'unit_price_minor' => $data->unitPriceMinor,
                'total_cost_minor' => $totalCostMinor,
                'currency' => $data->currency,
                'source' => $data->source,
                'supplier_id' => $data->supplierId,
                'store_requisition_id' => $requisitionId,
                'driver_id' => $data->driverId,
                'authorised_by' => $data->authorisedByUserId,
                'km_since_last' => $kmSinceLast,
                'km_per_litre' => $kmPerLitre,
                'variance_percent' => $variancePercent,
                'is_anomaly' => $isAnomaly,
                'journal_id' => $journalId,
            ]);

            if ($isAnomaly) {
                event(new FuelAnomalyDetected($fuelLog, implode(' ', $reasons)));
            }

            return $fuelLog;
        });
    }
}
