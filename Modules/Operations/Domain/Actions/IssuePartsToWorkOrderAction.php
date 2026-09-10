<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Operations\Domain\DataObjects\IssuePartsToWorkOrderData;
use Modules\Operations\Domain\Events\PartsIssuedToWorkOrder;
use Modules\Operations\Models\WorkOrder;
use Modules\Operations\Models\WorkOrderPart;
use Modules\Stores\Domain\Actions\ApproveStoreRequisitionAction;
use Modules\Stores\Domain\Actions\IssueStockAction;
use Modules\Stores\Domain\Actions\RequestStoreRequisitionAction;
use Modules\Stores\Domain\DataObjects\RequestStoreRequisitionData;

/**
 * ACT-IssuePartsToWorkOrder (Book H2 OPS-02 §3 ⭐/BR-OPS-02-005/
 * AC-OPS-02-002). The real `FIN-09` linkage — no shadow quantity/cost
 * pair kept independently. Drives a genuine `StoreRequisition`
 * against the work order's own cost centre through the same
 * request → approve → issue pipeline `FIN-09` itself uses, then reads
 * the real FIFO cost `IssueStockAction` posted back per line onto
 * `WorkOrderPart`. `IssueStockAction` already posts Dr expense/Cr
 * inventory against `requisition.cost_centre_id` — cost reaches the
 * ledger and the work order in the one path BR-OPS-02-005 requires.
 */
final class IssuePartsToWorkOrderAction extends Action
{
    public function __construct(
        private readonly RequestStoreRequisitionAction $requestRequisition,
        private readonly ApproveStoreRequisitionAction $approveRequisition,
        private readonly IssueStockAction $issueStock,
    ) {}

    public function execute(IssuePartsToWorkOrderData $data): WorkOrder
    {
        $workOrder = WorkOrder::findOrFail($data->workOrderId);

        return $this->transaction(function () use ($workOrder, $data): WorkOrder {
            $requisition = $this->requestRequisition->execute(new RequestStoreRequisitionData(
                schoolId: $workOrder->school_id,
                academicYearId: $workOrder->academic_year_id,
                termId: $workOrder->term_id,
                storeId: $data->storeId,
                costCentreId: $workOrder->cost_centre_id,
                purpose: "Work order {$workOrder->work_order_number}",
                requestedByUserId: $data->issuedByUserId,
                currency: $workOrder->currency,
                lines: array_map(
                    fn (array $l): array => ['itemId' => $l['itemId'], 'quantity' => $l['quantity'], 'unit' => $l['unit']],
                    $data->lines,
                ),
                sourceType: 'work_order',
                sourceId: $workOrder->id,
            ));

            $this->approveRequisition->execute($requisition->id, $data->issuedByUserId);
            $issued = $this->issueStock->execute($requisition->id, $data->issuedByUserId);

            $descriptionsByItemId = [];

            foreach ($data->lines as $line) {
                $descriptionsByItemId[$line['itemId']] = $line['description'];
            }

            $partsCostMinor = 0;

            foreach ($issued->lines as $line) {
                WorkOrderPart::create([
                    'school_id' => $workOrder->school_id,
                    'work_order_id' => $workOrder->id,
                    'item_id' => $line->item_id,
                    'description' => $descriptionsByItemId[$line->item_id] ?? '',
                    'quantity' => $line->quantity_issued,
                    'unit' => $line->unit,
                    'source' => 'store',
                    'store_requisition_id' => $requisition->id,
                    'unit_cost_minor' => $line->unit_cost_minor,
                    'line_cost_minor' => $line->line_cost_minor,
                    'issued_at' => now(),
                ]);

                $partsCostMinor += (int) $line->line_cost_minor;
            }

            $workOrder->update([
                'parts_cost_minor' => $workOrder->parts_cost_minor + $partsCostMinor,
                'total_cost_minor' => $workOrder->labour_cost_minor + $workOrder->parts_cost_minor + $partsCostMinor + $workOrder->contractor_cost_minor,
            ]);

            $workOrder->refresh();

            event(new PartsIssuedToWorkOrder($workOrder));

            return $workOrder;
        });
    }
}
