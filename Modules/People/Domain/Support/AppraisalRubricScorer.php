<?php

declare(strict_types=1);

namespace Modules\People\Domain\Support;

use InvalidArgumentException;
use Modules\People\Models\StaffAppraisalRubric;

/**
 * Validates a self/appraiser assessment's `scores` against the appraisal's own
 * rubric, the same way `RecordLessonObservationAction` (ACA-11) scores a lesson
 * observation: every criterion on the rubric must be scored, and each score must
 * be one of that criterion's own named levels. Shared between
 * `SubmitSelfAssessmentAction` and `SubmitAppraiserAssessmentAction` rather than
 * duplicated, since both validate the same rubric the same way.
 */
final class AppraisalRubricScorer
{
    /**
     * @param  array<string, mixed>  $assessment
     */
    public static function validate(?StaffAppraisalRubric $rubric, array $assessment): void
    {
        if ($rubric === null) {
            return;
        }

        $scores = $assessment['scores'] ?? null;

        if (! is_array($scores)) {
            throw new InvalidArgumentException('Score every criterion on the appraisal rubric.');
        }

        $levelsByCriterion = [];

        foreach ($rubric->criteria as $criterion) {
            $levelsByCriterion[(string) $criterion['criterion']] = (array) ($criterion['descriptor_levels'] ?? []);
        }

        if (array_diff(array_keys($levelsByCriterion), array_keys($scores)) !== []) {
            throw new InvalidArgumentException('Score every criterion on the appraisal rubric.');
        }

        foreach ($scores as $criterion => $level) {
            if (! isset($levelsByCriterion[(string) $criterion]) || ! in_array($level, $levelsByCriterion[(string) $criterion], true)) {
                throw new InvalidArgumentException("[{$level}] is not a level of the criterion [{$criterion}].");
            }
        }
    }
}
