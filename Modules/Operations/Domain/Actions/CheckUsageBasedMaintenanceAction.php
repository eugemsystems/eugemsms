<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Operations\Domain\DataObjects\CreateWorkOrderData;
use Modules\Operations\Domain\Events\PreventiveWorkOrderGenerated;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Operations\Models\MaintenanceSchedule;
use Modules\Operations\Models\WorkOrder;

/**
 * ACT-CheckUsageBasedMaintenance (Book H2 OPS-02 §6/BR-OPS-02-010,
 * Book H2 OPS-01 §4/BR-OPS-01-012 ⭐/AC-OPS-01-005). The usage-based
 * counterpart to `GeneratePreventiveWorkOrdersAction`'s own
 * calendar-only handling — called directly by `Modules\Transport`'s
 * `RecordTripOdometerAction` with the odometer reading, real wiring
 * rather than the deferral `GeneratePreventiveWorkOrdersAction`'s own
 * docblock still documents for `OPS-04`'s generator-hours side (that
 * module doesn't exist yet; `OPS-01` now does).
 */
final class CheckUsageBasedMaintenanceAction extends Action
{
    public function __construct(
        private readonly CreateWorkOrderAction $createWorkOrder,
    ) {}

    /**
     * @return Collection<int, WorkOrder>
     */
    public function execute(int $maintenanceAssetId, float $currentUnits, int $generatedByUserId): Collection
    {
        $asset = MaintenanceAsset::findOrFail($maintenanceAssetId);

        $due = MaintenanceSchedule::where('school_id', $asset->school_id)
            ->where('maintenance_asset_id', $asset->id)
            ->where('is_active', true)
            ->where('trigger_type', 'usage')
            ->whereNotNull('next_due_units')
            ->where('next_due_units', '<=', $currentUnits)
            ->get();

        if ($due->isEmpty()) {
            return new Collection;
        }

        $term = Term::where('school_id', $asset->school_id)->where('is_current', true)->firstOrFail();
        $currency = School::findOrFail($asset->school_id)->base_currency;

        return $this->transaction(function () use ($due, $asset, $term, $currency, $generatedByUserId): Collection {
            $workOrders = new Collection;

            foreach ($due as $schedule) {
                $workOrder = $this->createWorkOrder->execute(new CreateWorkOrderData(
                    schoolId: $asset->school_id,
                    academicYearId: $term->academic_year_id,
                    termId: $term->id,
                    workType: 'preventive',
                    title: $schedule->name,
                    description: "Usage-based preventive maintenance: {$schedule->name}.",
                    priority: 'normal',
                    assignedTeam: $schedule->assigned_team,
                    costCentreId: $asset->cost_centre_id,
                    currency: $currency,
                    raisedByUserId: $generatedByUserId,
                    maintenanceAssetId: $asset->id,
                    scheduleId: $schedule->id,
                    location: $asset->location,
                ));

                $schedule->update([
                    'next_due_units' => $schedule->interval_units !== null
                        ? $schedule->next_due_units + $schedule->interval_units
                        : $schedule->next_due_units,
                    'last_generated_wo_id' => $workOrder->id,
                ]);

                event(new PreventiveWorkOrderGenerated($workOrder));

                $workOrders->push($workOrder);
            }

            return $workOrders;
        });
    }
}
