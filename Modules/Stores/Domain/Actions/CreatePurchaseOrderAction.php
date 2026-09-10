<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Stores\Domain\DataObjects\CreatePurchaseOrderData;
use Modules\Stores\Models\PurchaseOrder;
use Modules\Stores\Models\PurchaseOrderLine;
use Modules\Stores\Models\Supplier;

/**
 * ACT-CreatePurchaseOrder (Book H1 FIN-08 §6/BR-FIN-08-011/026/
 * AC-FIN-08-012). Blacklisted suppliers cannot receive a new order —
 * checked here, before a number is even allocated, not left for
 * approval to catch. `po_number` is gapless via `CORE-06`; the budget
 * commitment itself waits for `ApprovePurchaseOrderAction`
 * (BR-FIN-08-010).
 */
final class CreatePurchaseOrderAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(CreatePurchaseOrderData $data): PurchaseOrder
    {
        $supplier = Supplier::findOrFail($data->supplierId);

        if (! $supplier->canReceiveOrders()) {
            throw ValidationException::withMessages([
                'supplierId' => "{$supplier->name} is {$supplier->status} and cannot receive a new purchase order (BR-FIN-08-026).",
            ]);
        }

        $subtotal = 0;
        $tax = 0;

        foreach ($data->lines as $line) {
            $lineTotal = (int) round($line['quantityOrdered'] * $line['unitPriceMinor']);
            $subtotal += $lineTotal;
            $tax += (int) round($lineTotal * $line['taxRatePercent'] / 100);
        }

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'purchase_order',
            allocatedByUserId: $data->createdByUserId,
            academicYearId: $data->academicYearId,
            termId: $data->termId,
        ));

        return $this->transaction(function () use ($data, $number, $subtotal, $tax): PurchaseOrder {
            $total = $subtotal + $tax;

            $order = PurchaseOrder::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'po_number' => $number->formatted_number,
                'supplier_id' => $data->supplierId,
                'requisition_id' => $data->requisitionId,
                'quotation_id' => $data->quotationId,
                'cost_centre_id' => $data->costCentreId,
                'budget_line_id' => $data->budgetLineId,
                'order_date' => $data->orderDate->toDateString(),
                'expected_delivery' => $data->expectedDelivery?->toDateString(),
                'subtotal_minor' => $subtotal,
                'tax_minor' => $tax,
                'total_minor' => $total,
                'currency' => $data->currency,
                'base_total_minor' => $total,
                'committed_minor' => 0,
                'status' => 'pending_approval',
                'created_by' => $data->createdByUserId,
            ]);

            foreach ($data->lines as $i => $line) {
                $lineTotal = (int) round($line['quantityOrdered'] * $line['unitPriceMinor']);

                PurchaseOrderLine::create([
                    'school_id' => $data->schoolId,
                    'purchase_order_id' => $order->id,
                    'line_number' => $i + 1,
                    'item_id' => $line['itemId'],
                    'description' => $line['description'],
                    'quantity_ordered' => $line['quantityOrdered'],
                    'unit' => $line['unit'],
                    'unit_price_minor' => $line['unitPriceMinor'],
                    'tax_rate_percent' => $line['taxRatePercent'],
                    'tax_category' => $line['taxCategory'],
                    'line_total_minor' => $lineTotal,
                    'expense_account_id' => $line['expenseAccountId'],
                    'is_capital' => $line['isCapital'],
                    'store_id' => $line['storeId'],
                ]);
            }

            return $order;
        });
    }
}
