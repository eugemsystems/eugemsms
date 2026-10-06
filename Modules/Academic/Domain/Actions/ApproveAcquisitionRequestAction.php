<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\ApproveAcquisitionRequestData;
use Modules\Academic\Models\AcquisitionRequest;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Department;
use Modules\Stores\Domain\Actions\RequestPurchaseRequisitionAction;
use Modules\Stores\Domain\DataObjects\RequestPurchaseRequisitionData;

/**
 * ACT-ApproveAcquisitionRequest (Book K ACA-10 §4/BR-ACA-10-010). Approval
 * hands the request to FIN-08's ordinary procurement pipeline as a purchase
 * requisition, linked back by `source_type`/`source_id`; the library runs no
 * parallel purchasing. The requisition still goes through FIN-08's own
 * approval levels and budget check.
 */
final class ApproveAcquisitionRequestAction extends Action
{
    public function __construct(
        private readonly RequestPurchaseRequisitionAction $requestRequisition,
    ) {}

    public function execute(ApproveAcquisitionRequestData $data): AcquisitionRequest
    {
        $request = AcquisitionRequest::findOrFail($data->requestId);

        if ($request->status !== 'requested') {
            throw new InvalidStateTransitionException(
                "Acquisition request #{$request->id} in [{$request->status}] cannot be approved.",
                ['request_id' => $request->id, 'status' => $request->status],
            );
        }

        if (strlen($data->currency) !== 3
            || ! Department::query()->whereKey($data->departmentId)->exists()
            || ! CostCentre::query()->whereKey($data->costCentreId)->exists()
            || ! AcademicYear::query()->whereKey($data->academicYearId)->exists()
            || ! Term::query()->whereKey($data->termId)->where('academic_year_id', $data->academicYearId)->exists()) {
            throw new InvalidArgumentException('Choose a department, cost centre, year, term and currency belonging to this school.');
        }

        return $this->transaction(function () use ($request, $data): AcquisitionRequest {
            $this->requestRequisition->execute(new RequestPurchaseRequisitionData(
                schoolId: $request->school_id,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                departmentId: $data->departmentId,
                costCentreId: $data->costCentreId,
                justification: "Library acquisition: {$request->requested_title}",
                requestedByUserId: $data->approvedByUserId,
                currency: $data->currency,
                lines: [[
                    'itemId' => null,
                    'description' => $request->requested_title,
                    'quantity' => (float) $request->copies_requested,
                    'unit' => 'each',
                    'estimatedUnitMinor' => $request->estimated_cost_minor === null ? null : intdiv($request->estimated_cost_minor, max(1, $request->copies_requested)),
                ]],
                sourceType: 'acquisition_request',
                sourceId: $request->id,
            ));

            $request->update(['status' => 'approved']);

            return $request->fresh();
        });
    }
}
