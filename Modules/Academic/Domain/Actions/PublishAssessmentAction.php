<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\PublishAssessmentData;
use Modules\Academic\Models\Assessment;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-PublishAssessment (Book D ACA-05 §3 step 1). Only a `published`
 * assessment's marks feed the aggregation pipeline
 * (`ComputeTermSubjectResultsAction`) — a merely `submitted` set of
 * marks is visible to staff but not yet counted toward a result.
 * `moderated`/`approved` are real states in the schema's own status
 * list but have no dedicated action in this pass — publishing
 * straight from `submitted` is the supported path; a school requiring
 * moderation sign-off first is a later addition, not a removed one.
 */
final class PublishAssessmentAction extends Action
{
    public function execute(PublishAssessmentData $data): Assessment
    {
        $assessment = Assessment::findOrFail($data->assessmentId);

        if (! in_array($assessment->status, ['submitted', 'moderated', 'approved'], true)) {
            throw new InvalidStateTransitionException(
                "An assessment in [{$assessment->status}] cannot be published.",
                ['assessment_id' => $assessment->id, 'status' => $assessment->status],
            );
        }

        return $this->transaction(function () use ($assessment, $data): Assessment {
            $assessment->update([
                'status' => 'published',
                'approved_by' => $assessment->approved_by ?? $data->publishedByUserId,
                'approved_at' => $assessment->approved_at ?? Carbon::now(),
                'published_at' => Carbon::now(),
            ]);

            return $assessment->fresh();
        });
    }
}
