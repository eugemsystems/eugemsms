<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Operations\Domain\DataObjects\CreateWorkOrderData;
use Modules\Operations\Domain\DataObjects\GeneratePreventiveWorkOrdersResult;
use Modules\Operations\Domain\Events\PreventiveWorkOrderGenerated;
use Modules\Operations\Models\MaintenanceSchedule;

/**
 * ACT-GeneratePreventiveWorkOrders (Book H2 OPS-02 §6/BR-OPS-02-009/
 * 010/AC-OPS-02-003). Only `trigger_type = 'calendar'` schedules fire
 * here — `BR-OPS-02-010`'s usage-based triggering (`OPS-01` odometer
 * readings, `OPS-04` generator hours) is a deliberate deferral:
 * neither module exists yet in this codebase. `next_due_on` advances
 * by `interval_days` from the due date itself (not from today), so a
 * missed run never compresses the following interval.
 */
final class GeneratePreventiveWorkOrdersAction extends Action
{
    public function __construct(
        private readonly CreateWorkOrderAction $createWorkOrder,
    ) {}

    public function execute(int $schoolId, int $generatedByUserId): GeneratePreventiveWorkOrdersResult
    {
        $today = Carbon::now();

        $due = MaintenanceSchedule::with('maintenanceAsset')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('trigger_type', 'calendar')
            ->whereNotNull('next_due_on')
            ->get()
            ->filter(fn (MaintenanceSchedule $schedule): bool => $schedule->next_due_on->subDays($schedule->lead_time_days)->lessThanOrEqualTo($today));

        if ($due->isEmpty()) {
            return new GeneratePreventiveWorkOrdersResult(new Collection);
        }

        $term = Term::where('school_id', $schoolId)->where('is_current', true)->firstOrFail();
        $currency = School::findOrFail($schoolId)->base_currency;

        $generated = $this->transaction(function () use ($due, $term, $currency, $generatedByUserId): Collection {
            $workOrders = new Collection;

            foreach ($due as $schedule) {
                $workOrder = $this->createWorkOrder->execute(new CreateWorkOrderData(
                    schoolId: $schedule->school_id,
                    academicYearId: $term->academic_year_id,
                    termId: $term->id,
                    workType: 'preventive',
                    title: $schedule->name,
                    description: "Scheduled preventive maintenance: {$schedule->name}.",
                    priority: 'normal',
                    assignedTeam: $schedule->assigned_team,
                    costCentreId: $schedule->maintenanceAsset->cost_centre_id,
                    currency: $currency,
                    raisedByUserId: $generatedByUserId,
                    maintenanceAssetId: $schedule->maintenance_asset_id,
                    scheduleId: $schedule->id,
                    location: $schedule->maintenanceAsset->location,
                    targetCompletion: $schedule->next_due_on,
                ));

                $schedule->update([
                    'next_due_on' => $schedule->interval_days !== null
                        ? $schedule->next_due_on->addDays($schedule->interval_days)
                        : $schedule->next_due_on,
                    'last_generated_wo_id' => $workOrder->id,
                ]);

                event(new PreventiveWorkOrderGenerated($workOrder));

                $workOrders->push($workOrder);
            }

            return $workOrders;
        });

        return new GeneratePreventiveWorkOrdersResult($generated);
    }
}
