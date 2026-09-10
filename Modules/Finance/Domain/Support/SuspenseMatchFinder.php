<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Modules\People\Models\Student;

/**
 * Book B FIN-04 §2/BR-FIN-04-011. Scored candidates against admission
 * number, learner surname, and payer name/phone text — never
 * auto-applied, the cashier always confirms. A simple, explainable
 * substring/similarity score, not a machine-learning match: good
 * enough to shortlist a handful of candidates out of a few hundred
 * learners, which is the whole of what the suspense workbench needs.
 */
final class SuspenseMatchFinder
{
    /**
     * @return array<int, array{student_id: int, admission_number: string, name: string, score: int}>
     */
    public function suggest(int $schoolId, ?string $referenceText, ?string $depositorName, int $limit = 5): array
    {
        $needle = trim(strtolower(($referenceText ?? '').' '.($depositorName ?? '')));

        if ($needle === '') {
            return [];
        }

        $candidates = Student::query()
            ->where('school_id', $schoolId)
            ->get(['id', 'admission_number', 'first_name', 'last_name'])
            ->map(function (Student $student) use ($needle): array {
                return [
                    'student_id' => $student->id,
                    'admission_number' => $student->admission_number,
                    'name' => $student->fullName(),
                    'score' => $this->score($needle, $student),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return $candidates->all();
    }

    private function score(string $needle, Student $student): int
    {
        $score = 0;

        if (str_contains($needle, strtolower($student->admission_number))) {
            $score += 100;
        }

        if (str_contains($needle, strtolower($student->last_name))) {
            $score += 50;
        }

        if (str_contains($needle, strtolower($student->first_name))) {
            $score += 20;
        }

        return $score;
    }
}
