<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-04 §4/BR-PPL-04-002. A staff member has at most one
 * active contract at a time — renew it instead of creating a second.
 */
class ActiveContractExistsException extends DomainException
{
    public static function forStaff(int $staffId): self
    {
        return new self(
            "Staff member [{$staffId}] already has an active contract — renew it instead of creating a new one.",
            ['staff_id' => $staffId],
        );
    }

    public function errorCode(): string
    {
        return 'ACTIVE_CONTRACT_EXISTS';
    }
}
