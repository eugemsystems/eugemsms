<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\RejectAcquisitionRequestData;
use Modules\Academic\Models\AcquisitionRequest;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

final class RejectAcquisitionRequestAction extends Action
{
    public function execute(RejectAcquisitionRequestData $data): AcquisitionRequest
    {
        $request = AcquisitionRequest::findOrFail($data->requestId);

        if ($request->status !== 'requested') {
            throw new InvalidStateTransitionException(
                "Acquisition request #{$request->id} in [{$request->status}] cannot be rejected.",
                ['request_id' => $request->id, 'status' => $request->status],
            );
        }

        return $this->transaction(function () use ($request): AcquisitionRequest {
            $request->update(['status' => 'rejected']);

            return $request->fresh();
        });
    }
}
