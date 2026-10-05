<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Operations\Domain\DataObjects\CreateMaintenanceScheduleData;
use Modules\Operations\Models\MaintenanceSchedule;

/**
 * ACT-CreateMaintenanceSchedule (Book H2 OPS-02 §2/BR-OPS-02-009/010).
 * Admin-UI-pass gap-fill, the same shape as `CreateMaintenanceAssetAction`'s
 * own docblock describes — `GeneratePreventiveWorkOrdersAction`/
 * `CheckUsageBasedMaintenanceAction` both read `maintenance_schedules`
 * but no Action ever created one; every row in the existing test
 * suite came from `MaintenanceScheduleFactory` directly. Create-only.
 */
final class CreateMaintenanceScheduleAction extends Action
{
    public function execute(CreateMaintenanceScheduleData $data): MaintenanceSchedule
    {
        return $this->transaction(fn (): MaintenanceSchedule => MaintenanceSchedule::create([
            'school_id' => $data->schoolId,
            'maintenance_asset_id' => $data->maintenanceAssetId,
            'name' => $data->name,
            'trigger_type' => $data->triggerType,
            'interval_days' => $data->intervalDays,
            'interval_units' => $data->intervalUnits,
            'lead_time_days' => $data->leadTimeDays,
            'task_checklist' => $data->taskChecklist,
            'estimated_hours' => $data->estimatedHours,
            'assigned_team' => $data->assignedTeam,
            'next_due_on' => $data->nextDueOn?->toDateString(),
            'next_due_units' => $data->nextDueUnits,
            'is_active' => true,
        ]));
    }
}
