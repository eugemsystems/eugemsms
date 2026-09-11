<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Academic\Models\CbtResponse;
use Modules\Academic\Models\CbtTest;
use Modules\Academic\Models\QuestionBankItem;

/**
 * Book K ACA-09 §4/BR-ACA-09-009. The classic upper-lower 27% method:
 * `difficulty_index` is the percentage of candidates who answered
 * correctly, `discrimination_index` is how much better the top 27% of
 * scorers did on this item than the bottom 27% — the standard measure
 * of whether an item actually separates strong from weak candidates.
 * Only auto-markable items are analysed; a written item's "correctness"
 * has no single objective definition to run this against.
 */
final class ItemAnalysisCalculator
{
    private const float GROUP_PROPORTION = 0.27;

    /**
     * @return array<int, array{questionId: int, difficultyIndex: float, discriminationIndex: float}>
     */
    public function compute(CbtTest $test): array
    {
        $attempts = $test->attempts()
            ->whereIn('status', ['auto_marked', 'fully_marked'])
            ->whereNotNull('raw_mark')
            ->orderByDesc('raw_mark')
            ->get();

        if ($attempts->count() < 2) {
            return [];
        }

        $groupSize = max(1, (int) round($attempts->count() * self::GROUP_PROPORTION));
        $topAttemptIds = $attempts->take($groupSize)->pluck('id');
        $bottomAttemptIds = $attempts->slice(-$groupSize)->pluck('id');
        $allAttemptIds = $attempts->pluck('id');

        $results = [];

        foreach (collect($test->question_ids ?? []) as $questionId) {
            $question = QuestionBankItem::find($questionId);

            if ($question === null || ! $question->is_auto_markable) {
                continue;
            }

            $responses = CbtResponse::query()
                ->where('question_id', $questionId)
                ->whereIn('attempt_id', $allAttemptIds)
                ->get();

            if ($responses->isEmpty()) {
                continue;
            }

            $results[] = [
                'questionId' => (int) $questionId,
                'difficultyIndex' => $this->correctRate($responses, $allAttemptIds),
                'discriminationIndex' => $this->correctRate($responses, $topAttemptIds) - $this->correctRate($responses, $bottomAttemptIds),
            ];
        }

        return $results;
    }

    /**
     * @param  Collection<int, CbtResponse>  $responses
     * @param  Collection<int, int>  $attemptIds
     */
    private function correctRate(Collection $responses, Collection $attemptIds): float
    {
        if ($attemptIds->isEmpty()) {
            return 0.0;
        }

        $inGroup = $responses->whereIn('attempt_id', $attemptIds);
        $correct = $inGroup->where('auto_mark_correct', true)->count();

        return round(($correct / $attemptIds->count()) * 100, 2);
    }
}
