<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-01-009. A line posted to a control account without a
 * `subledger_type`/`subledger_id` — e.g. a debtors control line with no
 * learner.
 */
class MissingSubledgerException extends DomainException
{
    public static function forAccount(string $code): self
    {
        return new self("Account [{$code}] is a control account and requires a subledger reference on every line.", ['account_code' => $code]);
    }

    public function errorCode(): string
    {
        return 'MISSING_SUBLEDGER';
    }
}
