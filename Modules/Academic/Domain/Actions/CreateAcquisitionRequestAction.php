<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateAcquisitionRequestData;
use Modules\Academic\Models\AcquisitionRequest;
use Modules\Core\Domain\Actions\Action;

final class CreateAcquisitionRequestAction extends Action
{
    public function execute(CreateAcquisitionRequestData $data): AcquisitionRequest
    {
        return $this->transaction(fn (): AcquisitionRequest => AcquisitionRequest::create([
            'school_id' => $data->schoolId,
            'requested_title' => $data->requestedTitle,
            'requested_by' => $data->requestedByUserId,
            'copies_requested' => $data->copiesRequested,
            'estimated_cost_minor' => $data->estimatedCostMinor,
            'status' => 'requested',
        ]));
    }
}
