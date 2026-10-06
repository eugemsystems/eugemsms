<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-07-009 ⭐ — an award that would exceed its scheme's capped budget
 * envelope is refused at grant, naming the shortfall.
 */
final class AwardBudgetEnvelopeExceededException extends DomainException
{
    public function errorCode(): string
    {
        return 'AWARD_BUDGET_ENVELOPE_EXCEEDED';
    }
}
