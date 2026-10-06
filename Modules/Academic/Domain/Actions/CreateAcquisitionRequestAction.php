<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateAcquisitionRequestData;
use Modules\Academic\Models\AcquisitionRequest;
use Modules\Core\Domain\Actions\Action;

final class CreateAcquisitionRequestAction extends Action
{
    public function execute(CreateAcquisitionRequestData $data): AcquisitionRequest
    {
        $title = trim($data->requestedTitle);

        if ($title === '' || $data->copiesRequested < 1 || $data->copiesRequested > 1000 || ($data->estimatedCostMinor !== null && $data->estimatedCostMinor < 0)) {
            throw new InvalidArgumentException('An acquisition request needs a title, 1-1000 copies and a non-negative cost.');
        }

        return $this->transaction(fn (): AcquisitionRequest => AcquisitionRequest::create([
            'school_id' => $data->schoolId,
            'requested_title' => $title,
            'requested_by' => $data->requestedByUserId,
            'copies_requested' => $data->copiesRequested,
            'estimated_cost_minor' => $data->estimatedCostMinor,
            'status' => 'requested',
        ]));
    }
}
