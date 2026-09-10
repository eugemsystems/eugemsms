<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-01-007. A line referenced an account belonging to a different
 * school from the journal.
 */
class CrossSchoolAccountException extends DomainException
{
    public static function forAccount(int $accountId): self
    {
        return new self("Account [{$accountId}] does not belong to this journal's school.", ['account_id' => $accountId]);
    }

    public function errorCode(): string
    {
        return 'CROSS_SCHOOL_ACCOUNT';
    }
}
