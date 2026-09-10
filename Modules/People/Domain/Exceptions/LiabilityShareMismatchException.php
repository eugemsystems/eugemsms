<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-03 §4/⭐/BR-PPL-03-006. `Σ(liability shares) ≡ Σ(learner
 * charges)`, per currency, always. The resolution algorithm is built
 * so this never actually fires — every minor unit assigned comes
 * directly out of `remaining` — but it is asserted anyway, because a
 * mismatch here would decide who gets billed wrongly, and that is
 * exactly the class of defect this rule exists to catch before it
 * reaches a family's invoice.
 */
class LiabilityShareMismatchException extends DomainException
{
    public static function forCurrency(string $currency, int $expectedMinor, int $actualMinor): self
    {
        return new self(
            "Liability shares for currency [{$currency}] total {$actualMinor}, expected {$expectedMinor}.",
            ['currency' => $currency, 'expected_minor' => $expectedMinor, 'actual_minor' => $actualMinor],
        );
    }

    public function errorCode(): string
    {
        return 'LIABILITY_SHARE_MISMATCH';
    }
}
