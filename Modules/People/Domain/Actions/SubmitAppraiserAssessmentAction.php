<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\SubmitAppraiserAssessmentData;
use Modules\People\Models\StaffAppraisal;

final class SubmitAppraiserAssessmentAction extends Action
{
    public function execute(SubmitAppraiserAssessmentData $data): StaffAppraisal
    {
        $appraisal = StaffAppraisal::findOrFail($data->appraisalId);

        if ($appraisal->status !== 'self_assessment') {
            throw new InvalidStateTransitionException(
                "An appraiser assessment can only be submitted from [self_assessment]; this appraisal is [{$appraisal->status}].",
                ['status' => $appraisal->status],
            );
        }

        return $this->transaction(function () use ($appraisal, $data): StaffAppraisal {
            $appraisal->update([
                'appraiser_assessment' => $data->appraiserAssessment,
                'overall_rating' => $data->overallRating,
                'development_plan' => $data->developmentPlan,
                'objectives' => $data->objectives,
                'status' => 'appraiser_review',
            ]);

            return $appraisal;
        });
    }
}
