<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\SignOffAppraisalData;
use Modules\People\Models\StaffAppraisal;

final class SignOffAppraisalAction extends Action
{
    public function execute(SignOffAppraisalData $data): StaffAppraisal
    {
        $appraisal = StaffAppraisal::findOrFail($data->appraisalId);

        if ($appraisal->status !== 'meeting_held') {
            throw new InvalidStateTransitionException(
                "An appraisal can only be signed off from [meeting_held]; this appraisal is [{$appraisal->status}].",
                ['status' => $appraisal->status],
            );
        }

        return $this->transaction(function () use ($appraisal, $data): StaffAppraisal {
            $appraisal->update([
                'staff_comments' => $data->staffComments,
                'status' => 'signed_off',
                'signed_off_at' => Carbon::now(),
            ]);

            return $appraisal;
        });
    }
}
