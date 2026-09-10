<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book B FIN-04 §5/BR-FIN-04-001 (AC-FIN-04-001). No cashier may
 * receipt outside an open till session assigned to them.
 */
class NoOpenTillSessionException extends DomainException
{
    public static function forSession(int $tillSessionId): self
    {
        return new self(
            "Till session [{$tillSessionId}] is not open — open a till session before receipting.",
            ['till_session_id' => $tillSessionId],
        );
    }

    public function errorCode(): string
    {
        return 'NO_OPEN_TILL_SESSION';
    }
}
