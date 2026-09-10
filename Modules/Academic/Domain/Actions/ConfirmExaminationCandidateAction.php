<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\ConfirmExaminationCandidateData;
use Modules\Academic\Domain\Support\IndexNumberAllocator;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\GradeLevel;
use Modules\People\Models\Student;

/**
 * ACT-ConfirmExaminationCandidate (Book E ACA-07 §2/§4/BR-ACA-07-001/002).
 * The exams officer's confirmation step — allocates the real, gapless
 * index number (`IndexNumberAllocator`) and freezes the candidate's
 * entry. Immutable once assigned: this action can only run once per
 * candidate.
 */
final class ConfirmExaminationCandidateAction extends Action
{
    public function __construct(
        private readonly IndexNumberAllocator $allocator,
    ) {}

    public function execute(ConfirmExaminationCandidateData $data): ExaminationCandidate
    {
        $candidate = ExaminationCandidate::findOrFail($data->candidateId);

        if ($candidate->entry_status === 'confirmed') {
            throw new InvalidStateTransitionException(
                "Candidate #{$candidate->id} is already confirmed — its index number is immutable.",
                ['candidate_id' => $candidate->id],
            );
        }

        $session = ExaminationSession::findOrFail($candidate->session_id);
        $student = Student::findOrFail($candidate->student_id);
        $gradeLevel = GradeLevel::findOrFail($student->grade_level_id);

        return $this->transaction(function () use ($candidate, $session, $gradeLevel, $data): ExaminationCandidate {
            $indexNumber = $this->allocator->allocate($session, $gradeLevel);

            $candidate->update([
                'index_number' => $indexNumber,
                'entry_status' => 'confirmed',
                'confirmed_by' => $data->confirmedByUserId,
                'confirmed_at' => Carbon::now(),
            ]);

            return $candidate;
        });
    }
}
