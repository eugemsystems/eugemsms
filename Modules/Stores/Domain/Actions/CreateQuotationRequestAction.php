<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Stores\Domain\DataObjects\CreateQuotationRequestData;
use Modules\Stores\Models\PurchaseRequisition;
use Modules\Stores\Models\QuotationRequest;

/**
 * ACT-CreateQuotationRequest (Book H1 FIN-08 §6/BR-FIN-08-007).
 * Refuses fewer than `procurement.minimum_quotations` invited
 * suppliers when the requisition's own estimate is at or above
 * `procurement.quotation_threshold_minor` — below threshold, a single
 * quotation (or none) is fine.
 */
final class CreateQuotationRequestAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(CreateQuotationRequestData $data): QuotationRequest
    {
        $requisition = PurchaseRequisition::findOrFail($data->requisitionId);
        $scope = new ScopeChain(schoolId: $data->schoolId);
        $threshold = (int) $this->settings->get('procurement.quotation_threshold_minor', $scope);
        $minimumQuotations = (int) $this->settings->get('procurement.minimum_quotations', $scope);

        if ($requisition->estimated_total_minor >= $threshold && count($data->supplierIds) < $minimumQuotations) {
            throw ValidationException::withMessages([
                'supplierIds' => "At least {$minimumQuotations} suppliers must be invited above the quotation threshold (BR-FIN-08-007).",
            ]);
        }

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'quotation_request',
            allocatedByUserId: $data->requestedByUserId,
        ));

        return $this->transaction(fn (): QuotationRequest => QuotationRequest::create([
            'school_id' => $data->schoolId,
            'requisition_id' => $requisition->id,
            'request_number' => $number->formatted_number,
            'suppliers_invited' => $data->supplierIds,
            'issued_on' => $data->issuedOn->toDateString(),
            'closes_on' => $data->closesOn->toDateString(),
            'status' => 'open',
        ]));
    }
}
