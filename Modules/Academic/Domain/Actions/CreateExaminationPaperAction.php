<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateExaminationPaperData;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateExaminationPaper (Book E ACA-07 §2/§5). Component weight
 * totalling 100% (BR-ACA-07-004) is deliberately NOT checked here —
 * papers are added one at a time and a partial set is normal mid-setup.
 * `ProcessExaminationResultsAction` checks the total at the point that
 * actually matters (AC-ACA-07-011).
 */
final class CreateExaminationPaperAction extends Action
{
    public function execute(CreateExaminationPaperData $data): ExaminationPaper
    {
        return $this->transaction(fn (): ExaminationPaper => ExaminationPaper::create([
            'school_id' => $data->schoolId,
            'session_id' => $data->sessionId,
            'subject_id' => $data->subjectId,
            'grade_level_id' => $data->gradeLevelId,
            'paper_number' => $data->paperNumber,
            'paper_name' => $data->paperName,
            'component_type' => $data->componentType,
            'max_mark' => $data->maxMark,
            'weight_percent' => $data->weightPercent,
            'duration_minutes' => $data->durationMinutes,
            'scheduled_date' => $data->scheduledDate,
            'scheduled_start' => $data->scheduledStart,
            'requires_special_venue' => $data->requiresSpecialVenue,
            'setter_staff_id' => $data->setterStaffId,
            'status' => 'draft',
        ]));
    }
}
