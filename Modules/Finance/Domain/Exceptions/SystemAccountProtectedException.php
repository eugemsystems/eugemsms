<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-01-019. A system account (`is_system = 1`) cannot be deleted,
 * recoded, or have its `system_key` changed.
 */
class SystemAccountProtectedException extends DomainException
{
    public static function forAccount(string $code): self
    {
        return new self("Account [{$code}] is a system account and cannot be deleted, recoded, or have its system_key changed.", ['account_code' => $code]);
    }

    public function errorCode(): string
    {
        return 'SYSTEM_ACCOUNT_PROTECTED';
    }
}
