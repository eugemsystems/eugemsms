<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateAssessmentInstrumentData;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateAssessmentInstrument (Book E ACA-06 §2/§3 ⭐/BR-ACA-06-001).
 * `AssessmentInstrument` is the abstract continuous-assessment
 * instrument; SBP is the current concrete instrument, CALA is
 * preserved read-only via `LegacyCalaRecord`.
 */
final class CreateAssessmentInstrumentAction extends Action
{
    public function execute(CreateAssessmentInstrumentData $data): AssessmentInstrument
    {
        return $this->transaction(fn (): AssessmentInstrument => AssessmentInstrument::create([
            'school_id' => $data->schoolId,
            'framework_id' => $data->frameworkId,
            'code' => $data->code,
            'name' => $data->name,
            'projects_per_subject_per_year' => $data->projectsPerSubjectPerYear,
            'applies_to_exam_classes' => $data->appliesToExamClasses,
            'contributes_to_final_mark' => $data->contributesToFinalMark,
            'default_weight_percent' => $data->defaultWeightPercent,
            'is_readonly' => $data->isReadonly,
            'reference_circular' => $data->referenceCircular,
            'status' => $data->status,
        ]));
    }
}
