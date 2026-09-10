<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book E ACA-07 §4/BR-ACA-07-013. "Neither marker sees the other's
 * mark until both have submitted" also means the same person cannot
 * be both the first and the second marker.
 */
class MarkerCannotDoubleAsSecondMarkerException extends DomainException
{
    public static function forCandidate(int $candidateId): self
    {
        return new self(
            "The first marker of candidate #{$candidateId} cannot also enter the second mark.",
            ['candidate_id' => $candidateId],
        );
    }

    public function errorCode(): string
    {
        return 'MARKER_CANNOT_DOUBLE_AS_SECOND_MARKER';
    }
}
