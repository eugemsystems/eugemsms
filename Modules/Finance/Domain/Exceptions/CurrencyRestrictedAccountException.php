<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-01-010. A line's currency doesn't match an account's fixed
 * `currency` restriction — e.g. a ZWG line against a USD-only bank
 * account.
 */
class CurrencyRestrictedAccountException extends DomainException
{
    public static function forAccount(string $code, string $restrictedTo, string $attempted): self
    {
        return new self(
            "Account [{$code}] only accepts {$restrictedTo}, not {$attempted}.",
            ['account_code' => $code, 'restricted_to' => $restrictedTo, 'attempted' => $attempted],
        );
    }

    public function errorCode(): string
    {
        return 'CURRENCY_RESTRICTED_ACCOUNT';
    }
}
