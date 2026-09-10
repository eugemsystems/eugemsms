<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\RecordDisciplinaryCommitteeData;
use Modules\Welfare\Models\DisciplinaryCommittee;

/**
 * ACT-RecordDisciplinaryCommittee (Book G BRD-07 §2/BR-BRD-07-006).
 * `learnerStatement` is a required string on this DTO — the caller
 * passes the literal text of a decline ("Declined to give a
 * statement.") when that's what happened, rather than this action
 * offering a way to omit it entirely.
 */
final class RecordDisciplinaryCommitteeAction extends Action
{
    public function execute(RecordDisciplinaryCommitteeData $data): DisciplinaryCommittee
    {
        return $this->transaction(fn (): DisciplinaryCommittee => DisciplinaryCommittee::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'convened_on' => $data->convenedOn->toDateString(),
            'panel_staff_ids' => $data->panelStaffIds,
            'guardian_present' => $data->guardianPresent,
            'learner_present' => $data->learnerPresent,
            'learner_statement' => $data->learnerStatement,
            'guardian_statement' => $data->guardianStatement,
            'evidence_reviewed' => $data->evidenceReviewed,
            'findings' => $data->findings,
            'decision' => $data->decision,
            'recommended_sanction_id' => $data->recommendedSanctionId,
            'chaired_by' => $data->chairedByUserId,
        ]));
    }
}
