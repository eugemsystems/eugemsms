<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book B FIN-04 §3/§5/BR-FIN-04-004 (AC-FIN-04-003). Variance beyond
 * tolerance requires a written reason and sign-off by a different
 * user holding `finance.till.supervise`.
 */
class VarianceSignOffRequiredException extends DomainException
{
    public static function missingReason(): self
    {
        return new self('A written reason is required to close this session — the variance exceeds tolerance.', []);
    }

    public static function missingSupervisor(): self
    {
        return new self('A different user must sign off this variance before the session can close.', []);
    }

    public static function supervisorMustDiffer(int $cashierId): self
    {
        return new self(
            "The supervisor signing off this variance must be a different user from the cashier [{$cashierId}].",
            ['cashier_id' => $cashierId],
        );
    }

    public function errorCode(): string
    {
        return 'VARIANCE_SIGN_OFF_REQUIRED';
    }
}
