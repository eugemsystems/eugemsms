<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateSubjectData;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateSubject (Book D ACA-01 §2/§5, `curriculum.manage`). New in
 * this admin-UI pass — every `Subject` row before now came from
 * `Subject::factory()` (tests and `TenantModelRegistry` demo data), not
 * a reviewable domain write path. Create-only: no `UpdateSubjectAction`
 * exists, matching this module's own established precedent (BR-ACA-01-005
 * — a subject cannot be deactivated while actively referenced — is left
 * for a future amendment action, not fabricated here).
 */
final class CreateSubjectAction extends Action
{
    public function execute(CreateSubjectData $data): Subject
    {
        return $this->transaction(fn (): Subject => Subject::create([
            'school_id' => $data->schoolId,
            'framework_id' => $data->frameworkId,
            'subject_group_id' => $data->subjectGroupId,
            'code' => $data->code,
            'name' => $data->name,
            'short_name' => $data->shortName,
            'zimsec_subject_code' => $data->zimsecSubjectCode,
            'cambridge_subject_code' => $data->cambridgeSubjectCode,
            'subject_type' => $data->subjectType,
            'is_examinable' => $data->isExaminable,
            'has_practical_component' => $data->hasPracticalComponent,
            'has_coursework' => $data->hasCoursework,
            'coursework_weight_percent' => $data->courseworkWeightPercent,
            'default_periods_per_week' => $data->defaultPeriodsPerWeek,
            'requires_sbp' => $data->requiresSbp,
            'grading_scale_id' => $data->gradingScaleId,
            'sort_order' => $data->sortOrder,
            'is_active' => true,
            'created_by' => $data->createdByUserId,
        ]));
    }
}
