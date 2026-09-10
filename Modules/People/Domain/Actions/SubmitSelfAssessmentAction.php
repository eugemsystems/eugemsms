<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\SubmitSelfAssessmentData;
use Modules\People\Models\StaffAppraisal;

final class SubmitSelfAssessmentAction extends Action
{
    public function execute(SubmitSelfAssessmentData $data): StaffAppraisal
    {
        $appraisal = StaffAppraisal::findOrFail($data->appraisalId);

        if ($appraisal->status !== 'draft') {
            throw new InvalidStateTransitionException(
                "A self-assessment can only be submitted from [draft]; this appraisal is [{$appraisal->status}].",
                ['status' => $appraisal->status],
            );
        }

        return $this->transaction(function () use ($appraisal, $data): StaffAppraisal {
            $appraisal->update(['self_assessment' => $data->selfAssessment, 'status' => 'self_assessment']);

            return $appraisal;
        });
    }
}
