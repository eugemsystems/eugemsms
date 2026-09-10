<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book B FIN-04 §5/BR-FIN-04-002. A cashier may hold at most one open
 * session at a time, at one till.
 */
class TillSessionAlreadyOpenException extends DomainException
{
    public static function forCashier(int $cashierId): self
    {
        return new self(
            "User [{$cashierId}] already has an open till session — close it before opening another.",
            ['cashier_id' => $cashierId],
        );
    }

    public function errorCode(): string
    {
        return 'TILL_SESSION_ALREADY_OPEN';
    }
}
