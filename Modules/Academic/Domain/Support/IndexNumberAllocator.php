<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Models\GradeLevel;

/**
 * Book E ACA-07 §4/BR-ACA-07-001. Gapless, immutable-once-assigned
 * index numbers, per session per grade level. Deliberately its own
 * small allocator rather than a reuse of Core CORE-06's
 * `NumberingSeries`/`AllocateNumberAction` — that machinery is scoped
 * to academic year/term and its pattern tokens (`{SCHOOL}`, `{YEAR}`,
 * `{TERM}`) don't cover `{CENTRE}`/`{LEVEL}`; extending CORE-06 to a
 * third scoping axis is out of this module's boundary. Callers must
 * invoke `allocate()` from inside their own transaction — this class
 * manages no transaction of its own, matching `TimetableClashDetector`'s
 * own stateless-service convention. The row lock this method takes
 * only holds until the caller's transaction commits, so two concurrent
 * confirmations for the same session/level can never walk away with
 * the same sequence.
 */
final class IndexNumberAllocator
{
    public function allocate(ExaminationSession $session, GradeLevel $gradeLevel): string
    {
        $sequence = ExaminationCandidate::query()
            ->where('session_id', $session->id)
            ->whereHas('student', fn ($q) => $q->where('grade_level_id', $gradeLevel->id))
            ->whereNotNull('confirmed_at')
            ->lockForUpdate()
            ->count() + 1;

        $pattern = $session->index_number_pattern ?? '{CENTRE}/{LEVEL}/{SEQ:4}';
        $centre = $session->school->code;
        $level = $gradeLevel->code;

        $formatted = strtr($pattern, ['{CENTRE}' => $centre, '{LEVEL}' => $level]);

        return (string) preg_replace_callback(
            '/\{SEQ:(\d+)\}/',
            fn (array $matches): string => str_pad((string) $sequence, (int) $matches[1], '0', STR_PAD_LEFT),
            $formatted,
        );
    }
}
