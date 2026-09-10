<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\StaffAppraisal;

final class RecordAppraisalMeetingAction extends Action
{
    public function execute(int $appraisalId): StaffAppraisal
    {
        $appraisal = StaffAppraisal::findOrFail($appraisalId);

        if ($appraisal->status !== 'appraiser_review') {
            throw new InvalidStateTransitionException(
                "The appraisal meeting can only be recorded from [appraiser_review]; this appraisal is [{$appraisal->status}].",
                ['status' => $appraisal->status],
            );
        }

        return $this->transaction(function () use ($appraisal): StaffAppraisal {
            $appraisal->update(['status' => 'meeting_held']);

            return $appraisal;
        });
    }
}
