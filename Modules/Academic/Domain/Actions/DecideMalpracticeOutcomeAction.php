<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\DecideMalpracticeOutcomeData;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\MalpracticeIncident;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-DecideMalpracticeOutcome (Book E ACA-07 §4/BR-ACA-07-018/AC-ACA-07-009).
 * A `paper_annulled` outcome voids that candidate's mark for the
 * paper; `session_annulled` voids every mark for every paper the
 * candidate sat in the session. A voided mark's `status` becomes
 * `void` — it stays a real, readable row (never deleted), and
 * `ProcessExaminationResultsAction` excludes `void` marks from
 * aggregation exactly as the rule requires.
 */
final class DecideMalpracticeOutcomeAction extends Action
{
    public function execute(DecideMalpracticeOutcomeData $data): MalpracticeIncident
    {
        $incident = MalpracticeIncident::findOrFail($data->incidentId);

        return $this->transaction(function () use ($incident, $data): MalpracticeIncident {
            $incident->update([
                'outcome' => $data->outcome,
                'outcome_by' => $data->outcomeByUserId,
                'investigation_notes' => $data->investigationNotes,
                'status' => 'decided',
            ]);

            if ($data->outcome === 'paper_annulled' && $incident->paper_id !== null && $incident->candidate_id !== null) {
                $this->voidMarks($incident->school_id, $incident->paper_id, $incident->candidate_id);
            }

            if ($data->outcome === 'session_annulled' && $incident->candidate_id !== null) {
                $paperIds = ExaminationMark::query()
                    ->where('school_id', $incident->school_id)
                    ->where('candidate_id', $incident->candidate_id)
                    ->pluck('paper_id');

                foreach ($paperIds as $paperId) {
                    $this->voidMarks($incident->school_id, $paperId, $incident->candidate_id);
                }
            }

            return $incident;
        });
    }

    private function voidMarks(int $schoolId, int $paperId, int $candidateId): void
    {
        ExaminationMark::query()
            ->where('school_id', $schoolId)
            ->where('paper_id', $paperId)
            ->where('candidate_id', $candidateId)
            ->update(['status' => 'void']);
    }
}
