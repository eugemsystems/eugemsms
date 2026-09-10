<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-01-020. An account cannot be deactivated while its balance is
 * non-zero or while any active subledger references it.
 */
class AccountHasBalanceException extends DomainException
{
    public static function forAccount(string $code): self
    {
        return new self("Account [{$code}] cannot be deactivated while its balance is non-zero.", ['account_code' => $code]);
    }

    public function errorCode(): string
    {
        return 'ACCOUNT_HAS_BALANCE';
    }
}
