<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Comms\Models\SurveyQuestion;

/**
 * Book I COM-08 §3 ⭐/BR-COM-08-002. "A respondent never sees a
 * question their prior answer made irrelevant" is enforced HERE,
 * server-side, not merely trusted from the client: any submitted
 * answer for a question this evaluator judges skipped is DROPPED
 * before it ever reaches `survey_response_answers` — a client that
 * ignored its own skip-logic rendering and submitted anyway still
 * can't make a skipped answer persist.
 */
final class SkipLogicEvaluator
{
    /**
     * @param  Collection<int, SurveyQuestion>  $questions  ordered by sequence
     * @param  array<int, mixed>  $submittedAnswersBySequence
     * @return array<int, mixed> only the answers for sequences that were actually reachable
     */
    public function filterActiveAnswers(Collection $questions, array $submittedAnswersBySequence): array
    {
        $active = [];
        $skipUntil = null;

        foreach ($questions as $question) {
            if ($skipUntil !== null && $question->sequence < $skipUntil) {
                continue;
            }

            $skipUntil = null;
            $answer = $submittedAnswersBySequence[$question->sequence] ?? null;

            if ($answer !== null) {
                $active[$question->sequence] = $answer;
            }

            $skipLogic = $question->skip_logic;

            if ($skipLogic !== null && $this->looseEquals($answer, $skipLogic['if_answer'])) {
                $skipUntil = (int) $skipLogic['go_to_sequence'];
            }
        }

        return $active;
    }

    private function looseEquals(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        return (string) $a === (string) $b;
    }
}
