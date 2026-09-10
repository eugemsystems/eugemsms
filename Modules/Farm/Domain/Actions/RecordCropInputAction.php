<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Farm\Domain\DataObjects\RecordCropInputData;
use Modules\Farm\Models\CropCycle;
use Modules\Farm\Models\CropInput;
use Modules\Stores\Domain\Actions\ApproveStoreRequisitionAction;
use Modules\Stores\Domain\Actions\IssueStockAction;
use Modules\Stores\Domain\Actions\RequestStoreRequisitionAction;
use Modules\Stores\Domain\DataObjects\RequestStoreRequisitionData;

/**
 * ACT-RecordCropInput (Book H2 OPS-03 §2 ⭐/BR-OPS-03-002). Draws from
 * the farm store through the real `FIN-09` request → approve → issue
 * pipeline, FIFO-costed by that engine — the same pattern this book's
 * other modules already use for parts/fuel/diesel.
 */
final class RecordCropInputAction extends Action
{
    public function __construct(
        private readonly RequestStoreRequisitionAction $requestRequisition,
        private readonly ApproveStoreRequisitionAction $approveRequisition,
        private readonly IssueStockAction $issueStock,
    ) {}

    public function execute(RecordCropInputData $data): CropInput
    {
        $cycle = CropCycle::findOrFail($data->cropCycleId);

        return $this->transaction(function () use ($data, $cycle): CropInput {
            $requisition = $this->requestRequisition->execute(new RequestStoreRequisitionData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                storeId: $data->storeId,
                costCentreId: $cycle->productionUnit->cost_centre_id,
                purpose: "Crop input — {$cycle->cycle_reference}",
                requestedByUserId: $data->appliedByUserId,
                currency: $cycle->currency,
                lines: [['itemId' => $data->itemId, 'quantity' => $data->quantity, 'unit' => $data->unit]],
            ));
            $this->approveRequisition->execute($requisition->id, $data->appliedByUserId);
            $issued = $this->issueStock->execute($requisition->id, $data->appliedByUserId);
            $line = $issued->lines->first();

            $costMinor = (int) $line->line_cost_minor;

            $input = CropInput::create([
                'school_id' => $data->schoolId,
                'crop_cycle_id' => $cycle->id,
                'input_type' => $data->inputType,
                'item_id' => $data->itemId,
                'description' => $data->description,
                'quantity' => $data->quantity,
                'unit' => $data->unit,
                'applied_on' => $data->appliedOn->toDateString(),
                'store_requisition_id' => $requisition->id,
                'cost_minor' => $costMinor,
                'currency' => $cycle->currency,
                'applied_by' => $data->appliedByUserId,
            ]);

            $inputCostMinor = $cycle->input_cost_minor + $costMinor;
            $cycle->update([
                'input_cost_minor' => $inputCostMinor,
                'total_cost_minor' => $inputCostMinor + $cycle->labour_cost_minor + $cycle->overhead_cost_minor,
            ]);

            return $input;
        });
    }
}
