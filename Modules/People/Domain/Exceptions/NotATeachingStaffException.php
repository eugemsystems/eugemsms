<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-04 §4/BR-PPL-04-005. A subject/class allocation may only
 * go to a staff member flagged `is_teaching`.
 */
class NotATeachingStaffException extends DomainException
{
    public static function forStaff(int $staffId): self
    {
        return new self(
            "Staff member [{$staffId}] is not flagged as teaching staff and cannot be allocated a subject.",
            ['staff_id' => $staffId],
        );
    }

    public function errorCode(): string
    {
        return 'NOT_A_TEACHING_STAFF';
    }
}
