<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateAssessmentData;
use Modules\Academic\Domain\DataObjects\CreateAssignmentData;
use Modules\Academic\Models\AssessmentType;
use Modules\Academic\Models\Assignment;
use Modules\Academic\Models\CourseSpace;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateAssignment (Book K ACA-08 §2/BR-ACA-08-008 ⭐). When
 * `assessmentTypeId` is given, this immediately provisions the
 * concrete `Assessment` row this assignment's marks will sync into
 * (via `CreateAssessmentAction`) — one assessable event per
 * assignment, never shared across two assignments of the same type.
 * See the `assignments` migration's docblock for why `assessment_id`
 * exists alongside the spec's own `assessment_type_id` column.
 */
final class CreateAssignmentAction extends Action
{
    public function __construct(
        private readonly CreateAssessmentAction $createAssessment,
    ) {}

    public function execute(CreateAssignmentData $data): Assignment
    {
        $courseSpace = CourseSpace::findOrFail($data->courseSpaceId);

        if (! in_array($data->latePolicy, ['block', 'accept_penalised', 'accept_flagged'], true)) {
            throw new InvalidArgumentException("Unknown late policy [{$data->latePolicy}].");
        }

        if ($data->latePolicy === 'accept_penalised' && $data->latePenaltyPercentPerDay === null) {
            throw new InvalidArgumentException('A penalty-per-day is required when the late policy is accept_penalised.');
        }

        if ($data->assessmentTypeId !== null && $data->maxMark === null) {
            throw new InvalidArgumentException('A max mark is required when an assignment feeds the gradebook.');
        }

        return $this->transaction(function () use ($data, $courseSpace): Assignment {
            $assessmentId = null;

            if ($data->assessmentTypeId !== null) {
                $assessmentType = AssessmentType::findOrFail($data->assessmentTypeId);

                $assessment = $this->createAssessment->execute(new CreateAssessmentData(
                    schoolId: $courseSpace->school_id,
                    academicYearId: $courseSpace->academic_year_id,
                    termId: $courseSpace->term_id,
                    assessmentTypeId: $assessmentType->id,
                    subjectId: $courseSpace->subject_id,
                    title: $data->title,
                    maxMark: $data->maxMark,
                    weightPercent: (float) $assessmentType->default_weight_percent,
                    createdByUserId: $data->createdByUserId,
                    teachingGroupId: $courseSpace->teaching_group_id,
                    assessedOn: $data->dueAt,
                ));

                $assessmentId = $assessment->id;
            }

            return Assignment::create([
                'school_id' => $courseSpace->school_id,
                'course_space_id' => $courseSpace->id,
                'title' => $data->title,
                'instructions' => $data->instructions,
                'attachment_file_ids' => $data->attachmentFileIds,
                'max_mark' => $data->maxMark,
                'rubric_id' => $data->rubricId,
                'assessment_type_id' => $data->assessmentTypeId,
                'assessment_id' => $assessmentId,
                'opens_at' => $data->opensAt,
                'due_at' => $data->dueAt,
                'late_policy' => $data->latePolicy,
                'late_penalty_percent_per_day' => $data->latePenaltyPercentPerDay,
                'allows_resubmission' => $data->allowsResubmission,
                'submission_type' => $data->submissionType,
                'status' => 'draft',
            ]);
        });
    }
}
