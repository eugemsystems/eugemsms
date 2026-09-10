<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book B FIN-04 §3/§5/BR-FIN-04-003 (AC-FIN-04-002). The expected
 * closing figure is inaccessible until the cashier's declaration is
 * submitted — enforced here, not by hiding a UI element.
 */
class TillDeclarationRequiredException extends DomainException
{
    public static function forSession(int $tillSessionId): self
    {
        return new self(
            "Till session [{$tillSessionId}] has no declared count yet — the expected figure cannot be revealed until one is submitted.",
            ['till_session_id' => $tillSessionId],
        );
    }

    public function errorCode(): string
    {
        return 'TILL_DECLARATION_REQUIRED';
    }
}
