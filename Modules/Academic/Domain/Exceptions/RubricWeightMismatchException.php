<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book E ACA-06 §6/BR-ACA-06-007. Rubric criterion weights must total
 * exactly 100%.
 */
class RubricWeightMismatchException extends DomainException
{
    public static function forTotal(float $total): self
    {
        return new self(
            "Rubric criterion weights must total 100%; they total {$total}%.",
            ['total_weight_percent' => $total],
        );
    }

    public function errorCode(): string
    {
        return 'RUBRIC_WEIGHT_MISMATCH';
    }
}
