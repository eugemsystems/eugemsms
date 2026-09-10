<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book E ACA-07 §4/BR-ACA-07-005.
 */
class VenueExamCapacityExceededException extends DomainException
{
    public static function forPaper(int $paperId, int $candidateCount, int $capacity): self
    {
        return new self(
            "Paper #{$paperId} has {$candidateCount} candidates but only {$capacity} exam seats across the given venues.",
            ['paper_id' => $paperId, 'candidate_count' => $candidateCount, 'capacity' => $capacity],
        );
    }

    public function errorCode(): string
    {
        return 'VENUE_EXAM_CAPACITY_EXCEEDED';
    }
}
