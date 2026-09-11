<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\MarkCbtResponseData;
use Modules\Academic\Domain\Exceptions\MarkOutOfRangeException;
use Modules\Academic\Models\CbtResponse;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-MarkCbtResponse (Book K ACA-09 §2/§4/BR-ACA-09-007). Manual
 * marking is only for written item types — an auto-markable item was
 * already marked at submission and is never re-marked here.
 */
final class MarkCbtResponseAction extends Action
{
    public function execute(MarkCbtResponseData $data): CbtResponse
    {
        $response = CbtResponse::with('attempt')->findOrFail($data->responseId);
        $question = QuestionBankItem::findOrFail($response->question_id);

        if ($question->is_auto_markable) {
            throw new InvalidArgumentException("Question #{$question->id} auto-marks and cannot be marked manually.");
        }

        if (! in_array($response->attempt->status, ['manual_marking_pending', 'fully_marked'], true)) {
            throw new InvalidStateTransitionException(
                "Attempt #{$response->attempt->id} has not been submitted yet and cannot be marked.",
                ['attempt_id' => $response->attempt->id, 'status' => $response->attempt->status],
            );
        }

        if ($data->markAwarded < 0 || $data->markAwarded > (float) $question->max_mark) {
            throw MarkOutOfRangeException::forMark($data->markAwarded, (float) $question->max_mark);
        }

        return $this->transaction(function () use ($response, $data): CbtResponse {
            $response->update([
                'mark_awarded' => $data->markAwarded,
                'manual_feedback' => $data->feedback,
                'marked_by' => $data->markedByUserId,
            ]);

            $this->recomputeAttempt($response->fresh());

            return $response->fresh();
        });
    }

    private function recomputeAttempt(CbtResponse $response): void
    {
        $attempt = $response->attempt;
        $test = $attempt->test;
        $questionIds = $test->question_ids ?? [];
        $questions = QuestionBankItem::query()->whereIn('id', $questionIds)->get()->keyBy('id');

        $rawMark = (float) CbtResponse::query()->where('attempt_id', $attempt->id)->whereNotNull('mark_awarded')->sum('mark_awarded');
        $totalMaxMark = (float) $questions->sum('max_mark');

        $manualResponsesStillPending = CbtResponse::query()
            ->where('attempt_id', $attempt->id)
            ->whereNull('mark_awarded')
            ->whereIn('question_id', $questions->where('is_auto_markable', false)->keys())
            ->exists();

        $attempt->update([
            'raw_mark' => $rawMark,
            'percent' => $totalMaxMark > 0 ? round(($rawMark / $totalMaxMark) * 100, 2) : null,
            'status' => $manualResponsesStillPending ? 'manual_marking_pending' : 'fully_marked',
        ]);
    }
}
