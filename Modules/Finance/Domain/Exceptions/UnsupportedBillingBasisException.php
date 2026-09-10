<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book B FIN-02 §3. `per_unit` and `usage_based` need a supplied
 * quantity from a requesting module (transport zone, wallet metering)
 * that doesn't exist yet; standalone `tiered` has no spec example
 * independent of `per_subject`'s own tier-band handling. All three are
 * deferred rather than guessed at.
 */
class UnsupportedBillingBasisException extends DomainException
{
    public static function forBasis(string $basis): self
    {
        return new self(
            "Billing basis [{$basis}] is not yet supported — it needs a module this pass does not build.",
            ['basis' => $basis],
        );
    }

    public function errorCode(): string
    {
        return 'UNSUPPORTED_BILLING_BASIS';
    }
}
