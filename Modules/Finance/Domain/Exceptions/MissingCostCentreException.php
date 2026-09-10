<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-01-008. A line targeted an account with
 * `requires_cost_centre = 1` but supplied none.
 */
class MissingCostCentreException extends DomainException
{
    public static function forAccount(string $code): self
    {
        return new self("Account [{$code}] requires a cost centre on every line.", ['account_code' => $code]);
    }

    public function errorCode(): string
    {
        return 'MISSING_COST_CENTRE';
    }
}
