<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Operations\Domain\DataObjects\CreateWorkOrderData;
use Modules\Operations\Domain\Exceptions\WorkOrderRequiresBudgetLineException;
use Modules\Operations\Models\FaultReport;
use Modules\Operations\Models\WorkOrder;
use Modules\Stores\Domain\Actions\CheckBudgetAvailabilityAction;

/**
 * ACT-CreateWorkOrder (Book H2 OPS-02 §6/BR-OPS-02-003/004). The
 * single entry point for every work order source in this module —
 * triage converting a fault report, a preventive schedule firing, or
 * a direct request. An estimate at or above
 * `maintenance.work_order_approval_threshold_minor` requires a budget
 * line (`WorkOrderRequiresBudgetLineException` otherwise) and is
 * checked for real against `FIN-11` availability
 * (`CheckBudgetAvailabilityAction`, the same one `FIN-08`'s own
 * requisition uses) — exceeding it never blocks creation here, it
 * only leaves the order `pending_approval` for `ApproveWorkOrderAction`
 * instead of starting `approved`, matching BR-FIN-11-009's own "never
 * blocked outright" doctrine.
 */
final class CreateWorkOrderAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly CheckBudgetAvailabilityAction $checkBudgetAvailability,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(CreateWorkOrderData $data, ?int $estimatedCostMinor = null): WorkOrder
    {
        $threshold = (int) $this->settings->get('maintenance.work_order_approval_threshold_minor', new ScopeChain(schoolId: $data->schoolId));
        $requiresApproval = $estimatedCostMinor !== null && $estimatedCostMinor >= $threshold;

        if ($requiresApproval && $data->budgetLineId === null) {
            throw WorkOrderRequiresBudgetLineException::aboveThreshold($estimatedCostMinor, $threshold);
        }

        if ($data->budgetLineId !== null) {
            $this->checkBudgetAvailability->execute($data->budgetLineId, $estimatedCostMinor ?? 0);
        }

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'work_order',
            allocatedByUserId: $data->raisedByUserId,
            academicYearId: $data->academicYearId,
            termId: $data->termId,
        ));

        return $this->transaction(function () use ($data, $number, $requiresApproval): WorkOrder {
            $workOrder = WorkOrder::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'work_order_number' => $number->formatted_number,
                'maintenance_asset_id' => $data->maintenanceAssetId,
                'fault_report_id' => $data->faultReportId,
                'schedule_id' => $data->scheduleId,
                'work_type' => $data->workType,
                'title' => $data->title,
                'description' => $data->description,
                'location' => $data->location,
                'priority' => $data->priority,
                'assigned_team' => $data->assignedTeam,
                'assigned_staff_id' => $data->assignedStaffId,
                'contractor_supplier_id' => $data->contractorSupplierId,
                'cost_centre_id' => $data->costCentreId,
                'budget_line_id' => $data->budgetLineId,
                'scheduled_for' => $data->scheduledFor?->toDateString(),
                'target_completion' => $data->targetCompletion?->toDateString(),
                'status' => $requiresApproval ? 'pending_approval' : 'approved',
                'labour_hours' => 0,
                'labour_cost_minor' => 0,
                'parts_cost_minor' => 0,
                'contractor_cost_minor' => 0,
                'total_cost_minor' => 0,
                'currency' => $data->currency,
                'raised_by' => $data->raisedByUserId,
            ]);

            if ($data->faultReportId !== null) {
                FaultReport::where('id', $data->faultReportId)->update([
                    'status' => 'work_order_raised',
                    'work_order_id' => $workOrder->id,
                ]);
            }

            return $workOrder;
        });
    }
}
