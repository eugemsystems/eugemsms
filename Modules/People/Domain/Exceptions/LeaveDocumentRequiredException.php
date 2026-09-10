<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-04 §4/BR-PPL-04-012. A leave type flagged
 * `requires_document` rejects submission without one, unless still
 * within the school's configured grace period.
 */
class LeaveDocumentRequiredException extends DomainException
{
    public static function forLeaveType(int $leaveTypeId): self
    {
        return new self(
            'This leave type requires a supporting document, or submission within the configured grace period.',
            ['leave_type_id' => $leaveTypeId],
        );
    }

    public function errorCode(): string
    {
        return 'LEAVE_DOCUMENT_REQUIRED';
    }
}
