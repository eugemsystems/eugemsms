<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book F BRD-03 §5/BR-BRD-03-006/007. Raised for suspension or fee
 * arrears blocks — the caller (not this action) is responsible for
 * routing an arrears block to the bursary rather than presenting it
 * as a generic system error (BR-BRD-03-006's own wording).
 */
class ExeatBlockedException extends DomainException
{
    public static function forReason(int $studentId, string $reason): self
    {
        return new self(
            "Student #{$studentId} is blocked from this exeat type: {$reason}.",
            ['student_id' => $studentId, 'reason' => $reason],
        );
    }

    public function errorCode(): string
    {
        return 'EXEAT_BLOCKED';
    }
}
