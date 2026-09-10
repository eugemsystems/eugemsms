<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book F BRD-03 §5/BR-BRD-03-005/AC-BRD-03-010.
 */
class ExeatQuotaExceededException extends DomainException
{
    public static function forStudent(int $studentId, int $exeatTypeId): self
    {
        return new self(
            "Student #{$studentId}'s quota for exeat type #{$exeatTypeId} is exhausted for this term.",
            ['student_id' => $studentId, 'exeat_type_id' => $exeatTypeId],
        );
    }

    public function errorCode(): string
    {
        return 'EXEAT_QUOTA_EXCEEDED';
    }
}
