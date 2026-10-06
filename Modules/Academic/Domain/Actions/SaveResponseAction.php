<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\SaveResponseData;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Academic\Models\CbtResponse;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-SaveResponse (Book K ACA-09 §3 ⭐/BR-ACA-09-001). The
 * server-side half of autosave — the client's own retry/local-queue
 * behaviour is out of scope here; this just needs to be safely
 * callable repeatedly for the same question (upsert, never a second
 * row) and to refuse writes once the attempt is no longer in progress.
 */
final class SaveResponseAction extends Action
{
    public function execute(SaveResponseData $data): CbtResponse
    {
        $attempt = CbtCandidateAttempt::with('test')->findOrFail($data->attemptId);

        if ($attempt->status !== 'in_progress') {
            throw new InvalidStateTransitionException(
                "Attempt #{$attempt->id} is no longer in progress and cannot accept new responses.",
                ['attempt_id' => $attempt->id, 'status' => $attempt->status],
            );
        }

        // Time is the server's call, never the candidate's clock (BR-ACA-09-002): once it has run
        // out, nothing more is accepted — what was saved by then is what gets marked.
        if ($attempt->remainingSeconds() <= 0) {
            throw new InvalidStateTransitionException(
                "Attempt #{$attempt->id} has run out of time and cannot accept new responses.",
                ['attempt_id' => $attempt->id],
            );
        }

        if (! in_array($data->questionId, $attempt->test->question_ids ?? [], true)) {
            throw new InvalidArgumentException('That question is not part of this test.');
        }

        return $this->transaction(function () use ($attempt, $data): CbtResponse {
            $response = CbtResponse::updateOrCreate(
                ['attempt_id' => $attempt->id, 'question_id' => $data->questionId],
                [
                    'school_id' => $attempt->school_id,
                    'response_value' => $data->responseValue,
                    'is_flagged_by_candidate' => $data->isFlaggedByCandidate,
                    'last_saved_at' => Carbon::now(),
                ],
            );

            $attempt->update(['last_autosave_at' => Carbon::now()]);

            return $response;
        });
    }
}
