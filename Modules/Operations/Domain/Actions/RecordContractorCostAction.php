<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Models\WorkOrder;
use Modules\Stores\Models\SupplierInvoice;

/**
 * ACT-RecordContractorCost (Book H2 OPS-02 §6/BR-OPS-02-007). A
 * deliberate, documented boundary: `Modules\Stores`' `supplier_invoices`
 * table (Book H1 FIN-08, already shipped and gated) carries no generic
 * `source_type`/`source_id` pair the way `store_requisitions` and
 * `purchase_requisitions` do, so there is no schema-level FK this
 * action can lean on the way `IssuePartsToWorkOrderAction` leans on
 * `store_requisition_id`. The caller registers and approves the
 * invoice through `FIN-08`'s own pipeline first, then supplies its id
 * here purely for traceability — verified to belong to the same
 * contractor and school, but not database-enforced as a link. Adding
 * that column to an already-gated Book H1 table is out of scope for
 * this module to decide unsupervised.
 */
final class RecordContractorCostAction extends Action
{
    public function execute(int $workOrderId, int $supplierInvoiceId, int $amountMinor, int $recordedByUserId): WorkOrder
    {
        $workOrder = WorkOrder::findOrFail($workOrderId);
        $invoice = SupplierInvoice::findOrFail($supplierInvoiceId);

        if ($invoice->school_id !== $workOrder->school_id || $invoice->supplier_id !== $workOrder->contractor_supplier_id) {
            throw new InvalidStateTransitionException(
                "Supplier invoice #{$invoice->id} does not belong to work order #{$workOrder->id}'s own contractor.",
                ['work_order_id' => $workOrder->id, 'supplier_invoice_id' => $invoice->id],
            );
        }

        return $this->transaction(function () use ($workOrder, $amountMinor): WorkOrder {
            $contractorCostMinor = $workOrder->contractor_cost_minor + $amountMinor;

            $workOrder->update([
                'contractor_cost_minor' => $contractorCostMinor,
                'total_cost_minor' => $workOrder->labour_cost_minor + $workOrder->parts_cost_minor + $contractorCostMinor,
            ]);

            return $workOrder->refresh();
        });
    }
}
