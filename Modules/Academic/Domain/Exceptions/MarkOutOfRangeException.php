<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-05 §5/BR-ACA-05-007.
 */
class MarkOutOfRangeException extends DomainException
{
    public static function forMark(float $rawMark, float $maxMark): self
    {
        return new self(
            "A mark of {$rawMark} is outside the valid range 0-{$maxMark}.",
            ['raw_mark' => $rawMark, 'max_mark' => $maxMark],
        );
    }

    public function errorCode(): string
    {
        return 'MARK_OUT_OF_RANGE';
    }
}
