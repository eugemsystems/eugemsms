<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\ReportMalpracticeIncidentData;
use Modules\Academic\Domain\Events\MalpracticeReported;
use Modules\Academic\Models\MalpracticeIncident;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ReportMalpracticeIncident (Book E ACA-07 §4/BR-ACA-07-019).
 * Always confidential by default (`is_confidential`) — visibility to
 * only the head, deputy, and exams officer is enforced at the
 * Livewire/API layer (no screens built yet in this pass), not here.
 */
final class ReportMalpracticeIncidentAction extends Action
{
    public function execute(ReportMalpracticeIncidentData $data): MalpracticeIncident
    {
        return $this->transaction(function () use ($data): MalpracticeIncident {
            $incident = MalpracticeIncident::create([
                'school_id' => $data->schoolId,
                'session_id' => $data->sessionId,
                'paper_id' => $data->paperId,
                'candidate_id' => $data->candidateId,
                'incident_type' => $data->incidentType,
                'description' => $data->description,
                'evidence_file_ids' => $data->evidenceFileIds,
                'reported_by' => $data->reportedByUserId,
                'occurred_at' => $data->occurredAt,
                'status' => 'reported',
                'is_confidential' => true,
            ]);

            event(new MalpracticeReported($incident));

            return $incident;
        });
    }
}
