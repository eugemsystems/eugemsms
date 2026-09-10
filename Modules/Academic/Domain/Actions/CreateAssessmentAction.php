<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateAssessmentData;
use Modules\Academic\Models\Assessment;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateAssessment (Book D ACA-05 §2/BR-ACA-05-004). Weight-total
 * completeness (every assessment for a subject/term summing to 100%)
 * is checked at computation time by `ComputeTermSubjectResultsAction`,
 * not here — an assessment plan is built up one assessment at a time
 * and no intermediate state is expected to already total 100%.
 */
final class CreateAssessmentAction extends Action
{
    public function execute(CreateAssessmentData $data): Assessment
    {
        return $this->transaction(fn (): Assessment => Assessment::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'assessment_type_id' => $data->assessmentTypeId,
            'subject_id' => $data->subjectId,
            'grade_level_id' => $data->gradeLevelId,
            'class_id' => $data->classId,
            'teaching_group_id' => $data->teachingGroupId,
            'title' => $data->title,
            'max_mark' => $data->maxMark,
            'weight_percent' => $data->weightPercent,
            'assessed_on' => $data->assessedOn?->toDateString(),
            'grading_scale_id' => $data->gradingScaleId,
            'status' => 'draft',
            'created_by' => $data->createdByUserId,
        ]));
    }
}
