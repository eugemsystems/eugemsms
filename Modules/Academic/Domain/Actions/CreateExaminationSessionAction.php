<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateExaminationSessionData;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateExaminationSession (Book E ACA-07 §2/§5).
 */
final class CreateExaminationSessionAction extends Action
{
    public function execute(CreateExaminationSessionData $data): ExaminationSession
    {
        return $this->transaction(fn (): ExaminationSession => ExaminationSession::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'name' => $data->name,
            'exam_type' => $data->examType,
            'exam_body' => $data->examBody,
            'affected_levels' => $data->affectedLevels,
            'starts_on' => $data->startsOn,
            'ends_on' => $data->endsOn,
            'index_number_pattern' => $data->indexNumberPattern,
            'exam_slot_plan_id' => $data->examSlotPlanId,
            'status' => 'planning',
            'created_by' => $data->createdBy,
        ]));
    }
}
