<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\AdvanceExaminationSessionStatusData;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-AdvanceExaminationSessionStatus (Book E admin-UI pass, ACA-07 §2).
 * `examination_sessions.status` moves planning → entries_open →
 * entries_closed → scheduled → in_progress → marking → moderation →
 * results_ready → published → archived (§2's own column comment), but
 * only `ProcessExaminationResultsAction` (→ results_ready, and only from
 * in_progress/marking/moderation) and `PublishExaminationResultsAction`
 * (→ published, only from results_ready) ever move it — nothing takes a
 * fresh session from `planning` to `in_progress` so those two actions
 * have something to act on. A genuine gap, the same shape as this pass's
 * `CreateTimetableAction`: a small, forward-only, one-step-at-a-time
 * transition with no business logic beyond "is this the literal next
 * status in the sequence", added because the UI genuinely needs it and
 * nothing in the domain layer had reason to build it yet.
 */
final class AdvanceExaminationSessionStatusAction extends Action
{
    private const array SEQUENCE = [
        'planning', 'entries_open', 'entries_closed', 'scheduled',
        'in_progress', 'marking', 'moderation',
    ];

    public function execute(AdvanceExaminationSessionStatusData $data): ExaminationSession
    {
        $session = ExaminationSession::findOrFail($data->sessionId);

        $currentIndex = array_search($session->status, self::SEQUENCE, true);
        $targetIndex = array_search($data->toStatus, self::SEQUENCE, true);

        if ($currentIndex === false || $targetIndex === false || $targetIndex !== $currentIndex + 1) {
            throw new InvalidStateTransitionException(
                "Session #{$session->id} cannot move from {$session->status} to {$data->toStatus} — only the next stage in the sequence is allowed.",
                ['session_id' => $session->id, 'from' => $session->status, 'to' => $data->toStatus],
            );
        }

        return $this->transaction(fn (): ExaminationSession => tap($session)->update(['status' => $data->toStatus]));
    }
}
