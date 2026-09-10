<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 FIN-14 §5/BR-FIN-14-006 (AC-FIN-14-003). Refused with the
 * remaining allowance shown.
 */
final class SpendingLimitExceededException extends DomainException
{
    public static function forLimit(string $limitType, int $remainingMinor): self
    {
        return new self(
            "This purchase exceeds the {$limitType} spending limit — {$remainingMinor} minor units remaining.",
            ['limit_type' => $limitType, 'remaining_minor' => $remainingMinor],
        );
    }

    public function errorCode(): string
    {
        return 'WALLET_SPENDING_LIMIT_EXCEEDED';
    }
}
