<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\AssignInvigilationData;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\InvigilationAssignment;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\People\Models\TeacherAllocation;

/**
 * ACT-AssignInvigilation (Book E ACA-07 §4/BR-ACA-07-010/011).
 * Excludes a teacher of the subject being examined by default —
 * mirrors `AllocateTeacherAction::$overrideCeiling`'s own pattern:
 * refuse unless the caller explicitly acknowledges staffing does not
 * permit avoidance (`$data->overrideSubjectTeacherExclusion`).
 * `duty_assignment_id` links back to an already-created `PPL-04`
 * `DutyAssignment` so exam duty counts toward that module's own
 * fairness balancing — this action does not create the roster entry
 * itself, only the link.
 */
final class AssignInvigilationAction extends Action
{
    public function execute(AssignInvigilationData $data): InvigilationAssignment
    {
        $paper = ExaminationPaper::findOrFail($data->paperId);

        $teachesSubject = TeacherAllocation::query()
            ->where('staff_id', $data->staffId)
            ->where('subject_id', $paper->subject_id)
            ->exists();

        if ($teachesSubject && ! $data->overrideSubjectTeacherExclusion) {
            throw new class("Staff #{$data->staffId} teaches subject #{$paper->subject_id} and is excluded from invigilating paper #{$paper->id} (BR-ACA-07-010) unless explicitly overridden.") extends DomainException
            {
                public function errorCode(): string
                {
                    return 'INVIGILATOR_TEACHES_EXAM_SUBJECT';
                }
            };
        }

        return $this->transaction(fn (): InvigilationAssignment => InvigilationAssignment::updateOrCreate(
            ['school_id' => $paper->school_id, 'paper_id' => $paper->id, 'venue_id' => $data->venueId, 'staff_id' => $data->staffId],
            [
                'role' => $data->role,
                'duty_assignment_id' => $data->dutyAssignmentId,
                'confirmed' => false,
                'report_submitted' => false,
            ],
        ));
    }
}
