<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-01-021. An account cannot be deleted once any line references
 * it — deactivation only.
 */
class AccountReferencedException extends DomainException
{
    public static function forAccount(string $code): self
    {
        return new self("Account [{$code}] has journal lines and can never be deleted — deactivate it instead.", ['account_code' => $code]);
    }

    public function errorCode(): string
    {
        return 'ACCOUNT_REFERENCED';
    }
}
