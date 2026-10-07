<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\ModerateAssessmentData;
use Modules\Academic\Models\Assessment;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ModerateAssessment (Book D ACA-05 §5, `academic.result.moderate`). `moderated` is a real
 * state in `assessments.status`'s own enum (confirmed in `PublishAssessmentAction`'s docblock,
 * which already accepts it as a valid pre-publish status) but had no Action ever setting it until
 * now. Moderation is optional, not mandatory: `PublishAssessmentAction` still publishes straight
 * from `submitted` too — this is the sign-off a school chooses to require, not a forced gate.
 */
final class ModerateAssessmentAction extends Action
{
    public function execute(ModerateAssessmentData $data): Assessment
    {
        $assessment = Assessment::findOrFail($data->assessmentId);

        if ($assessment->status !== 'submitted') {
            throw new InvalidStateTransitionException(
                "An assessment must be [submitted] before it can be moderated; this one is [{$assessment->status}].",
                ['assessment_id' => $assessment->id, 'status' => $assessment->status],
            );
        }

        return $this->transaction(function () use ($assessment, $data): Assessment {
            $assessment->update([
                'status' => 'moderated',
                'moderated_by' => $data->moderatedByUserId,
                'moderated_at' => Carbon::now(),
                'moderation_note' => $data->moderationNote,
            ]);

            return $assessment->fresh();
        });
    }
}
