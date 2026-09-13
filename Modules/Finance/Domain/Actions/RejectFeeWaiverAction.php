<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\DataObjects\RejectFeeWaiverData;
use Modules\Finance\Models\FeeWaiver;

final class RejectFeeWaiverAction extends Action
{
    public function execute(RejectFeeWaiverData $data): FeeWaiver
    {
        $waiver = FeeWaiver::findOrFail($data->feeWaiverId);

        if ($waiver->status !== 'pending') {
            throw new InvalidStateTransitionException(
                "Only a pending waiver/write-off can be rejected — this one is {$waiver->status}.",
                ['fee_waiver_id' => $waiver->id],
            );
        }

        return $this->transaction(fn (): FeeWaiver => tap($waiver)->update([
            'status' => 'rejected',
            'approved_by' => $data->rejectedByUserId,
        ]));
    }
}
