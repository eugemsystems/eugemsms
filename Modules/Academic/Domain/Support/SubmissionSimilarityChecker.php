<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Academic\Models\AssignmentSubmission;

/**
 * Book K ACA-08 §4/BR-ACA-08-006. Compares a submission's text against
 * every other submission in the SAME class, SAME assignment, using a
 * simple word-shingle Jaccard similarity — cheap enough to run
 * synchronously on every submit, no external service required. This
 * is advisory only (never auto-penalises): a match above the
 * configured threshold just flags both submissions for the teacher's
 * own judgement.
 */
final class SubmissionSimilarityChecker
{
    private const int SHINGLE_SIZE = 5;

    /**
     * @param  Collection<int, AssignmentSubmission>  $otherSubmissions
     * @return array<int, int> ids of other submissions this one matches at or above the threshold
     */
    public function findMatches(AssignmentSubmission $submission, Collection $otherSubmissions, int $thresholdPercent): array
    {
        if ($submission->submitted_text === null || trim($submission->submitted_text) === '') {
            return [];
        }

        $ownShingles = $this->shingle($submission->submitted_text);

        if ($ownShingles === []) {
            return [];
        }

        $matches = [];

        foreach ($otherSubmissions as $other) {
            if ($other->id === $submission->id || $other->submitted_text === null) {
                continue;
            }

            $otherShingles = $this->shingle($other->submitted_text);

            if ($otherShingles === [] || $this->jaccardPercent($ownShingles, $otherShingles) < $thresholdPercent) {
                continue;
            }

            $matches[] = $other->id;
        }

        return $matches;
    }

    /**
     * @return array<int, string>
     */
    private function shingle(string $text): array
    {
        $words = preg_split('/\s+/', mb_strtolower(trim($text))) ?: [];
        $words = array_values(array_filter($words, fn (string $w): bool => $w !== ''));

        if (count($words) < self::SHINGLE_SIZE) {
            return $words === [] ? [] : [implode(' ', $words)];
        }

        $shingles = [];

        for ($i = 0; $i <= count($words) - self::SHINGLE_SIZE; $i++) {
            $shingles[] = implode(' ', array_slice($words, $i, self::SHINGLE_SIZE));
        }

        return $shingles;
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    private function jaccardPercent(array $a, array $b): int
    {
        $setA = array_unique($a);
        $setB = array_unique($b);
        $union = count(array_unique([...$setA, ...$setB]));

        if ($union === 0) {
            return 0;
        }

        $intersection = count(array_intersect($setA, $setB));

        return (int) round(($intersection / $union) * 100);
    }
}
