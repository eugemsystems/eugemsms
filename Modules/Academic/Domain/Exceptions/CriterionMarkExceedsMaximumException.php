<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book E ACA-06 §6/BR-ACA-06-007. A criterion mark must not exceed
 * that criterion's own maximum.
 */
class CriterionMarkExceedsMaximumException extends DomainException
{
    public static function forCriterion(string $criterion, float $mark, float $maxMark): self
    {
        return new self(
            "A mark of {$mark} for criterion \"{$criterion}\" exceeds its maximum of {$maxMark}.",
            ['criterion' => $criterion, 'mark' => $mark, 'max_mark' => $maxMark],
        );
    }

    public function errorCode(): string
    {
        return 'CRITERION_MARK_EXCEEDS_MAXIMUM';
    }
}
