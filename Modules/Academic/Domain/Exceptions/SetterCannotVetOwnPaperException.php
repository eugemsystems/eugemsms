<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book E ACA-07 §3/BR-ACA-07 "setter separation"/AC-ACA-07-003.
 */
class SetterCannotVetOwnPaperException extends DomainException
{
    public static function forPaper(int $paperId): self
    {
        return new self(
            "Paper #{$paperId}'s setter cannot also vet it.",
            ['paper_id' => $paperId],
        );
    }

    public function errorCode(): string
    {
        return 'SETTER_CANNOT_VET_OWN_PAPER';
    }
}
