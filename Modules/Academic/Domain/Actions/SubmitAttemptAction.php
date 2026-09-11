<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\SubmitAttemptData;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Academic\Models\CbtResponse;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-SubmitAttempt (Book K ACA-09 §3/§4/BR-ACA-09-006/007/AC-ACA-09-006).
 * Objective items mark instantly here; written items are left
 * unmarked, pushing the attempt to `manual_marking_pending` rather
 * than `auto_marked`. Called with `autoSubmitted: true` for the
 * time-expiry path — whatever was saved by then is what gets marked,
 * never anything lost, never anything extra accepted after.
 */
final class SubmitAttemptAction extends Action
{
    public function execute(SubmitAttemptData $data): CbtCandidateAttempt
    {
        $attempt = CbtCandidateAttempt::with('test')->findOrFail($data->attemptId);

        if (! in_array($attempt->status, ['in_progress', 'flagged'], true)) {
            throw new InvalidStateTransitionException(
                "Attempt #{$attempt->id} in [{$attempt->status}] cannot be submitted.",
                ['attempt_id' => $attempt->id, 'status' => $attempt->status],
            );
        }

        return $this->transaction(function () use ($attempt, $data): CbtCandidateAttempt {
            $questionIds = $attempt->test->question_ids ?? [];
            $questions = QuestionBankItem::query()->whereIn('id', $questionIds)->get()->keyBy('id');
            $responses = CbtResponse::query()->where('attempt_id', $attempt->id)->get();

            foreach ($responses as $response) {
                $question = $questions->get($response->question_id);

                if ($question === null || ! $question->is_auto_markable) {
                    continue;
                }

                $correct = $this->responseMatchesCorrectAnswer($response->response_value, $question->correct_answer);

                $response->update([
                    'auto_mark_correct' => $correct,
                    'mark_awarded' => $correct ? $question->max_mark : 0,
                ]);
            }

            $rawMark = (float) CbtResponse::query()->where('attempt_id', $attempt->id)->whereNotNull('mark_awarded')->sum('mark_awarded');
            $totalMaxMark = (float) $questions->sum('max_mark');
            $needsManualMarking = $questions->contains(fn (QuestionBankItem $q): bool => ! $q->is_auto_markable);

            $attempt->update([
                'submitted_at' => Carbon::now(),
                'auto_submitted' => $data->autoSubmitted,
                'raw_mark' => $rawMark,
                'percent' => $totalMaxMark > 0 ? round(($rawMark / $totalMaxMark) * 100, 2) : null,
                'status' => $needsManualMarking ? 'manual_marking_pending' : 'auto_marked',
            ]);

            return $attempt->fresh();
        });
    }

    private function responseMatchesCorrectAnswer(mixed $response, mixed $correctAnswer): bool
    {
        if (is_array($response) && is_array($correctAnswer)) {
            $normalise = fn (array $values): array => collect($values)->sort()->values()->all();

            return $normalise($response) === $normalise($correctAnswer);
        }

        return $response === $correctAnswer;
    }
}
