<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\VerifyProjectData;
use Modules\Academic\Domain\Events\LearnerProjectVerified;
use Modules\Academic\Models\LearnerProject;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-VerifyProject (Book E ACA-06 §5/§6/BR-ACA-06-014). HOD sign-off
 * — only a `verified` outcome counts toward the final mark
 * (`ContinuousAssessmentProvider::outcomeFor()`). A project not
 * sampled for moderation may be verified straight from `marked`.
 */
final class VerifyProjectAction extends Action
{
    public function execute(VerifyProjectData $data): LearnerProject
    {
        $learnerProject = LearnerProject::findOrFail($data->learnerProjectId);

        if (! in_array($learnerProject->status, ['marked', 'moderated'], true)) {
            throw new InvalidStateTransitionException(
                "A project must be marked or moderated before it can be verified (currently {$learnerProject->status}).",
                ['learner_project_id' => $learnerProject->id, 'status' => $learnerProject->status],
            );
        }

        return $this->transaction(function () use ($learnerProject, $data): LearnerProject {
            $learnerProject->update([
                'status' => 'verified',
                'verified_by' => $data->verifiedByUserId,
                'verified_at' => Carbon::now(),
            ]);

            event(new LearnerProjectVerified($learnerProject));

            return $learnerProject;
        });
    }
}
