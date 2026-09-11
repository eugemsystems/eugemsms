<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Modules\Academic\Models\SpecialArrangement;

/**
 * Book K ACA-09 §4/BR-ACA-09-003. This module maintains no
 * accommodations register of its own — it reads the learner's most
 * recently approved `extra_time` arrangement straight out of `ACA-07`'s
 * `special_arrangements`, wherever it was originally raised. A
 * candidate with no approved arrangement gets zero extra minutes.
 */
final class ExtraTimeResolver
{
    public function minutesFor(int $studentId, int $baseDurationMinutes): int
    {
        $arrangement = SpecialArrangement::query()
            ->where('student_id', $studentId)
            ->where('arrangement_type', 'extra_time')
            ->where('status', 'approved')
            ->whereNotNull('extra_time_percent')
            ->orderByDesc('approved_at')
            ->first();

        if ($arrangement === null || $arrangement->extra_time_percent === null) {
            return 0;
        }

        return (int) round($baseDurationMinutes * ($arrangement->extra_time_percent / 100));
    }
}
