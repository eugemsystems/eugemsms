<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Stores\Domain\DataObjects\RequestStoreRequisitionData;
use Modules\Stores\Models\StoreRequisition;
use Modules\Stores\Models\StoreRequisitionLine;

/**
 * ACT-RequestStoreRequisition (Book H1 FIN-09 §2). `requisition_number`
 * is gapless via `CORE-06`.
 */
final class RequestStoreRequisitionAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(RequestStoreRequisitionData $data): StoreRequisition
    {
        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'store_requisition',
            allocatedByUserId: $data->requestedByUserId,
            academicYearId: $data->academicYearId,
            termId: $data->termId,
        ));

        return $this->transaction(function () use ($data, $number): StoreRequisition {
            $requisition = StoreRequisition::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'requisition_number' => $number->formatted_number,
                'store_id' => $data->storeId,
                'requesting_department_id' => $data->requestingDepartmentId,
                'cost_centre_id' => $data->costCentreId,
                'purpose' => $data->purpose,
                'required_by' => $data->requiredBy?->toDateString(),
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
                'status' => 'pending',
                'requested_by' => $data->requestedByUserId,
                'currency' => $data->currency,
            ]);

            foreach ($data->lines as $line) {
                StoreRequisitionLine::create([
                    'school_id' => $data->schoolId,
                    'requisition_id' => $requisition->id,
                    'item_id' => $line['itemId'],
                    'quantity_requested' => $line['quantity'],
                    'unit' => $line['unit'],
                ]);
            }

            return $requisition;
        });
    }
}
