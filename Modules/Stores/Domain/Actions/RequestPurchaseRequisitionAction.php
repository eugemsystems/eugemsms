<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Stores\Domain\DataObjects\RequestPurchaseRequisitionData;
use Modules\Stores\Models\PurchaseRequisition;
use Modules\Stores\Models\PurchaseRequisitionLine;

/**
 * ACT-RequestPurchaseRequisition (Book H1 FIN-08 §2/BR-FIN-08-006/
 * FIN-11 §3/BR-FIN-11-009/AC-FIN-11-004). `budget_check_result` is a
 * real check against `FIN-11`'s own `CheckBudgetAvailabilityAction`
 * when the caller names a `budgetLineId` — `not_checked` only when
 * none is given (a requisition not tied to any budget line at all).
 * Exceeding available budget never blocks submission here — it is
 * recorded as `exceeds` for a higher approval level to see, exactly
 * as BR-FIN-11-009 requires.
 */
final class RequestPurchaseRequisitionAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly CheckBudgetAvailabilityAction $checkBudgetAvailability,
    ) {}

    public function execute(RequestPurchaseRequisitionData $data): PurchaseRequisition
    {
        $estimatedTotal = 0;

        foreach ($data->lines as $line) {
            $estimatedTotal += $line['estimatedUnitMinor'] !== null
                ? (int) round($line['quantity'] * $line['estimatedUnitMinor'])
                : 0;
        }

        $availability = $this->checkBudgetAvailability->execute($data->budgetLineId, $estimatedTotal);

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'purchase_requisition',
            allocatedByUserId: $data->requestedByUserId,
            academicYearId: $data->academicYearId,
            termId: $data->termId,
        ));

        return $this->transaction(function () use ($data, $number, $estimatedTotal, $availability): PurchaseRequisition {
            $requisition = PurchaseRequisition::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'requisition_number' => $number->formatted_number,
                'department_id' => $data->departmentId,
                'cost_centre_id' => $data->costCentreId,
                'budget_line_id' => $data->budgetLineId,
                'justification' => $data->justification,
                'required_by' => $data->requiredBy?->toDateString(),
                'urgency' => $data->urgency,
                'estimated_total_minor' => $estimatedTotal,
                'currency' => $data->currency,
                'budget_available_minor' => $availability->availableMinor,
                'budget_check_result' => $availability->result,
                'status' => 'pending',
                'requested_by' => $data->requestedByUserId,
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
            ]);

            foreach ($data->lines as $line) {
                PurchaseRequisitionLine::create([
                    'school_id' => $data->schoolId,
                    'requisition_id' => $requisition->id,
                    'item_id' => $line['itemId'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'estimated_unit_minor' => $line['estimatedUnitMinor'],
                    'estimated_total_minor' => $line['estimatedUnitMinor'] !== null
                        ? (int) round($line['quantity'] * $line['estimatedUnitMinor'])
                        : null,
                    'currency' => $data->currency,
                ]);
            }

            return $requisition;
        });
    }
}
