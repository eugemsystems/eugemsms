<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-01-006. A line targeted a header/rollup account.
 */
class AccountNotPostableException extends DomainException
{
    public static function forAccount(string $code): self
    {
        return new self("Account [{$code}] is not postable — it is a header/rollup account.", ['account_code' => $code]);
    }

    public function errorCode(): string
    {
        return 'ACCOUNT_NOT_POSTABLE';
    }
}
