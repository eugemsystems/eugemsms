<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\PublishExaminationResultsData;
use Modules\Academic\Domain\Events\ExamResultsPublished;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-PublishExaminationResults (Book E ACA-07 §4/BR-ACA-07-017/
 * AC-ACA-07-008). Staged release: `results_ready` (internal review)
 * → `published` (visible to learners/guardians). Marking completion
 * (`results_ready`) never publishes anything by itself.
 */
final class PublishExaminationResultsAction extends Action
{
    public function execute(PublishExaminationResultsData $data): ExaminationSession
    {
        $session = ExaminationSession::findOrFail($data->sessionId);

        if ($session->status !== 'results_ready') {
            throw new InvalidStateTransitionException(
                "Session #{$session->id} must be results_ready before it can be published (currently {$session->status}).",
                ['session_id' => $session->id, 'status' => $session->status],
            );
        }

        return $this->transaction(function () use ($session): ExaminationSession {
            $session->update(['status' => 'published']);

            event(new ExamResultsPublished($session));

            return $session;
        });
    }
}
