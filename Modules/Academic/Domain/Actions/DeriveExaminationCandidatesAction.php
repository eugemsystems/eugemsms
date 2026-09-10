<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\DeriveExaminationCandidatesData;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-DeriveExaminationCandidates (Book E ACA-07 §4/BR-ACA-07-002/
 * AC-ACA-07-001-ish). Candidate entry is derived from `ACA-02`
 * enrolments, never typed from scratch — this action reads
 * `learner_subject_enrolments` for every learner at an affected grade
 * level and produces one `provisional` `ExaminationCandidate` per
 * learner with their currently-enrolled subjects snapshotted into
 * `entered_subjects`. Re-running it for learners already derived is
 * a no-op (idempotent via `firstOrCreate`); it never removes an
 * already-derived candidate — `ConfirmExaminationCandidateAction`
 * and the exams officer are the only ones who act on the entry from
 * here.
 */
final class DeriveExaminationCandidatesAction extends Action
{
    /**
     * @return Collection<int, ExaminationCandidate>
     */
    public function execute(DeriveExaminationCandidatesData $data): Collection
    {
        $session = ExaminationSession::findOrFail($data->sessionId);

        $enrolments = LearnerSubjectEnrolment::query()
            ->where('school_id', $session->school_id)
            ->where('academic_year_id', $session->academic_year_id)
            ->where('status', 'active')
            ->whereHas('student', fn ($q) => $q->whereIn('grade_level_id', $session->affected_levels))
            ->get()
            ->groupBy('student_id');

        return $this->transaction(function () use ($session, $enrolments): Collection {
            return $enrolments->map(function (Collection $studentEnrolments, int $studentId) use ($session): ExaminationCandidate {
                return ExaminationCandidate::firstOrCreate(
                    ['school_id' => $session->school_id, 'session_id' => $session->id, 'student_id' => $studentId],
                    [
                        'index_number' => 'PENDING-'.$studentId,
                        'entry_status' => 'provisional',
                        'entered_subjects' => $studentEnrolments->pluck('subject_id')->unique()->values()->all(),
                        'entry_invoiced' => false,
                    ],
                );
            })->values();
        });
    }
}
