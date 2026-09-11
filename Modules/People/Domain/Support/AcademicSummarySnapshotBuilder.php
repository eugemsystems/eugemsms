<?php

declare(strict_types=1);

namespace Modules\People\Domain\Support;

use Modules\Academic\Models\TermResult;
use Modules\Sport\Models\Award;

/**
 * Book K PPL-06 §3 ⭐/BR-PPL-06-002. Reads `ACA-05`'s `term_results`
 * and `OPS-07`'s `awards` exactly ONCE, at graduation, and returns a
 * plain array meant to be stored verbatim as
 * `alumni.academic_summary_snapshot` — never re-derived later. A
 * school's grading scale changing five years on must not silently
 * reinterpret a frozen alumnus record (AC-PPL-06-002).
 */
final class AcademicSummarySnapshotBuilder
{
    /**
     * @return array{terms: array<int, array<string, mixed>>, final_average_percent: string|null, honours: array<int, array<string, mixed>>}
     */
    public function build(int $studentId): array
    {
        $termResults = TermResult::query()
            ->where('student_id', $studentId)
            ->join('terms', 'terms.id', '=', 'term_results.term_id')
            ->orderBy('terms.starts_on')
            ->select('term_results.*')
            ->get();

        $terms = $termResults->map(fn (TermResult $result): array => [
            'term_id' => $result->term_id,
            'academic_year_id' => $result->academic_year_id,
            'average_percent' => $result->average_percent,
            'class_position' => $result->class_position,
            'class_size' => $result->class_size,
            'level_position' => $result->level_position,
            'level_size' => $result->level_size,
            'subjects_taken' => $result->subjects_taken,
            'subjects_passed' => $result->subjects_passed,
        ])->all();

        $honours = Award::query()
            ->where('student_id', $studentId)
            ->get()
            ->map(fn (Award $award): array => [
                'award_type' => $award->award_type,
                'title' => $award->title,
                'citation' => $award->citation,
                'awarded_on' => $award->awarded_on->toDateString(),
            ])
            ->all();

        return [
            'terms' => $terms,
            'final_average_percent' => $termResults->last()?->average_percent,
            'honours' => $honours,
        ];
    }
}
