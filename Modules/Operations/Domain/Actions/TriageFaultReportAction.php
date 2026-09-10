<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Domain\DataObjects\CreateWorkOrderData;
use Modules\Operations\Domain\DataObjects\TriageFaultReportData;
use Modules\Operations\Models\FaultReport;
use Modules\Operations\Models\WorkOrder;

/**
 * ACT-TriageFaultReport (Book H2 OPS-02 §6/BR-OPS-02-003). One of the
 * three triage outcomes — the other two are
 * `MarkFaultReportDuplicateAction` and `RejectFaultReportAction`.
 * Converting builds a `WorkOrder` from the report's own facts plus
 * the triage-supplied work-order specifics, then routes through
 * `CreateWorkOrderAction` so `BR-OPS-02-004`'s approval/budget check
 * applies exactly as it does for a standalone work order.
 */
final class TriageFaultReportAction extends Action
{
    public function __construct(
        private readonly CreateWorkOrderAction $createWorkOrder,
    ) {}

    public function execute(int $faultReportId, TriageFaultReportData $data): WorkOrder
    {
        $report = FaultReport::findOrFail($faultReportId);

        if ($report->status !== 'reported') {
            throw new InvalidStateTransitionException(
                "Fault report #{$report->id} must be reported to be triaged (currently {$report->status}).",
                ['fault_report_id' => $report->id, 'status' => $report->status],
            );
        }

        return $this->transaction(function () use ($report, $data): WorkOrder {
            $workOrder = $this->createWorkOrder->execute(new CreateWorkOrderData(
                schoolId: $report->school_id,
                academicYearId: $data->academicYearId,
                termId: $report->term_id,
                workType: $data->workType,
                title: $data->title,
                description: $report->description,
                priority: $data->priority,
                assignedTeam: $data->assignedTeam,
                costCentreId: $data->costCentreId,
                currency: $data->currency,
                raisedByUserId: $report->reported_by,
                maintenanceAssetId: $report->maintenance_asset_id,
                faultReportId: $report->id,
                location: $report->location,
                assignedStaffId: $data->assignedStaffId,
                contractorSupplierId: $data->contractorSupplierId,
                budgetLineId: $data->budgetLineId,
                scheduledFor: $data->scheduledFor,
                targetCompletion: $data->targetCompletion,
            ), $data->estimatedCostMinor);

            $report->update([
                'triaged_by' => $data->triagedByUserId,
                'triage_note' => "Converted to work order {$workOrder->work_order_number}.",
            ]);

            return $workOrder;
        });
    }
}
