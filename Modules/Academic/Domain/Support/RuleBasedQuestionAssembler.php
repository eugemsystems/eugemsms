<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use InvalidArgumentException;
use Modules\Academic\Domain\Exceptions\InsufficientQuestionBankException;
use Modules\Academic\Models\QuestionBankItem;

/**
 * Book K ACA-09 §4/BR-ACA-09-005. Draws a concrete question set from
 * the bank matching a rule's difficulty mix and (optional) topic
 * coverage. A bank short of what a rule demands fails loudly — this
 * never silently repeats a question to make up the count.
 *
 * `$rules` arrives as a caller-supplied, untyped JSON-shaped array
 * (it's stored verbatim in `cbt_tests.assembly_rules`), so its shape
 * is validated here at runtime rather than declared as a PHPStan array
 * shape — a DTO property can't statically narrow to one.
 */
final class RuleBasedQuestionAssembler
{
    /**
     * @param  array<string, mixed>  $rules  {count:int, mix:array<string,float>, topics?:array<int,string>}
     * @return array<int, int> question_bank ids, one draw per rule slot
     */
    public function assemble(int $schoolId, int $subjectId, array $rules): array
    {
        if (! isset($rules['count'], $rules['mix']) || ! is_int($rules['count']) || ! is_array($rules['mix'])) {
            throw new InvalidArgumentException('A rule-based assembly requires a "count" (int) and a "mix" (difficulty => proportion).');
        }

        $count = $rules['count'];
        $mix = $rules['mix'];
        $topics = is_array($rules['topics'] ?? null) ? $rules['topics'] : null;

        $questionIds = [];

        foreach ($mix as $difficulty => $proportion) {
            $difficulty = (string) $difficulty;
            $required = (int) round($count * (float) $proportion);

            if ($required === 0) {
                continue;
            }

            $query = QuestionBankItem::query()
                ->where('school_id', $schoolId)
                ->where('subject_id', $subjectId)
                ->where('difficulty', $difficulty)
                ->where('is_active', true);

            if ($topics !== null && $topics !== []) {
                $query->whereIn('topic', $topics);
            }

            $available = $query->pluck('id');

            if ($available->count() < $required) {
                throw InsufficientQuestionBankException::forDifficulty($difficulty, $required, $available->count());
            }

            $questionIds = [...$questionIds, ...$available->shuffle()->take($required)->all()];
        }

        return $questionIds;
    }
}
