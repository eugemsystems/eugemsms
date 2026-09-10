<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\ExemptLearnerProjectData;
use Modules\Academic\Models\LearnerProject;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ExemptLearnerProject (Book E ACA-06 §5/BR-ACA-06-006). A
 * learner dropping the subject has their project set to `exempt`
 * with the reason recorded — never deleted. Called directly by staff
 * action and by `ExemptProjectOnSubjectDropListener`.
 */
final class ExemptLearnerProjectAction extends Action
{
    public function execute(ExemptLearnerProjectData $data): LearnerProject
    {
        $learnerProject = LearnerProject::findOrFail($data->learnerProjectId);

        return $this->transaction(function () use ($learnerProject, $data): LearnerProject {
            $learnerProject->update([
                'status' => 'exempt',
                'exemption_reason' => $data->exemptionReason,
            ]);

            return $learnerProject;
        });
    }
}
