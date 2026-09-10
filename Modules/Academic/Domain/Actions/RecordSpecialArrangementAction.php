<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\RecordSpecialArrangementData;
use Modules\Academic\Models\SpecialArrangement;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordSpecialArrangement (Book E ACA-07 §4/BR-ACA-07-009).
 * Requested state — `ApproveSpecialArrangementAction` is the only way
 * it becomes effective for seating/invigilation.
 */
final class RecordSpecialArrangementAction extends Action
{
    public function execute(RecordSpecialArrangementData $data): SpecialArrangement
    {
        return $this->transaction(fn (): SpecialArrangement => SpecialArrangement::create([
            'school_id' => $data->schoolId,
            'session_id' => $data->sessionId,
            'student_id' => $data->studentId,
            'arrangement_type' => $data->arrangementType,
            'extra_time_percent' => $data->extraTimePercent,
            'justification' => $data->justification,
            'supporting_document_id' => $data->supportingDocumentId,
            'applies_to_papers' => $data->appliesToPapers,
            'status' => 'requested',
        ]));
    }
}
