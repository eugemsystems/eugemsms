<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\School;
use Modules\Operations\Models\WorkOrder;
use Modules\Stores\Models\DepreciationEntry;
use Modules\Transport\Domain\DataObjects\RouteCostingResult;
use Modules\Transport\Models\FuelLog;
use Modules\Transport\Models\LearnerTransport;
use Modules\Transport\Models\Route;
use Modules\Transport\Models\VehicleCompliance;

/**
 * ACT-ComputeRouteCosting (Book H2 OPS-01 §4/BR-OPS-01-018). Read-only
 * — aggregates real figures from `FIN-09`/`OPS-02`/`FIN-10` against
 * the route's transport fee income, answering whether the route is
 * viable. Fee income is the zone's own termly rate (actual billed
 * amounts stay a `FIN-02` deferral — see `LearnerAssignedToRoute`'s
 * own docblock).
 */
final class ComputeRouteCostingAction extends Action
{
    public function execute(int $routeId, CarbonInterface $periodStart, CarbonInterface $periodEnd): RouteCostingResult
    {
        $route = Route::findOrFail($routeId);
        $vehicle = $route->assignedVehicle;

        $fuelCostMinor = $vehicle !== null
            ? (int) FuelLog::where('vehicle_id', $vehicle->id)->whereBetween('fuelled_at', [$periodStart, $periodEnd])->sum('total_cost_minor')
            : 0;

        $maintenanceCostMinor = $vehicle?->maintenance_asset_id !== null
            ? (int) WorkOrder::where('maintenance_asset_id', $vehicle->maintenance_asset_id)
                ->whereBetween('completed_at', [$periodStart, $periodEnd])
                ->sum('total_cost_minor')
            : 0;

        $complianceCostMinor = $vehicle !== null
            ? (int) VehicleCompliance::where('vehicle_id', $vehicle->id)
                ->whereBetween('issued_on', [$periodStart, $periodEnd])
                ->sum('cost_minor')
            : 0;

        $depreciationMinor = $vehicle?->fixed_asset_id !== null
            ? (int) DepreciationEntry::whereHas('run', fn ($query) => $query->whereBetween('run_date', [$periodStart, $periodEnd]))
                ->where('asset_id', $vehicle->fixed_asset_id)
                ->sum('depreciation_minor')
            : 0;

        $feeIncomeMinor = (int) LearnerTransport::where('route_id', $route->id)
            ->where('status', 'active')
            ->with('zone')
            ->get()
            ->sum(fn (LearnerTransport $assignment): int => $assignment->zone->termly_fee_minor);

        return new RouteCostingResult(
            routeId: $route->id,
            fuelCostMinor: $fuelCostMinor,
            maintenanceCostMinor: $maintenanceCostMinor,
            complianceCostMinor: $complianceCostMinor,
            depreciationMinor: $depreciationMinor,
            feeIncomeMinor: $feeIncomeMinor,
            currency: School::findOrFail($route->school_id)->base_currency,
        );
    }
}
