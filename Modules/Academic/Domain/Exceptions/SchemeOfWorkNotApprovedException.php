<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-11 §3/BR-ACA-11-001/AC-ACA-11-001. A lesson plan linked
 * to a scheme of work cannot be submitted until that scheme is
 * HOD-approved.
 */
class SchemeOfWorkNotApprovedException extends DomainException
{
    public static function forScheme(int $schemeOfWorkId, string $status): self
    {
        return new self(
            "Scheme of work #{$schemeOfWorkId} is [{$status}], not approved — a linked lesson plan cannot be submitted yet.",
            ['scheme_of_work_id' => $schemeOfWorkId, 'status' => $status],
        );
    }

    public function errorCode(): string
    {
        return 'SCHEME_OF_WORK_NOT_APPROVED';
    }
}
