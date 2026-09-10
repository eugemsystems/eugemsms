<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * §11 `finance.rounding_tolerance_minor` / BR-FIN-01-005. A
 * base-currency residual larger than the configured tolerance is
 * refused rather than silently rounded away — it's evidence of a rate
 * or input error, not a rounding quirk.
 */
class RoundingToleranceExceededException extends DomainException
{
    public static function forResidual(int $residualMinor, int $toleranceMinor): self
    {
        return new self(
            "Base-currency rounding residual of {$residualMinor} exceeds the tolerance of {$toleranceMinor}.",
            ['residual_minor' => $residualMinor, 'tolerance_minor' => $toleranceMinor],
        );
    }

    public function errorCode(): string
    {
        return 'ROUNDING_TOLERANCE_EXCEEDED';
    }
}
