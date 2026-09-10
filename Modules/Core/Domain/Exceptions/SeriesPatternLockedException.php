<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-06-005 — a numbering pattern cannot be changed once numbers
 * have been allocated in the current period. It takes effect from the
 * next reset boundary instead.
 */
class SeriesPatternLockedException extends DomainException
{
    public function errorCode(): string
    {
        return 'SERIES_PATTERN_LOCKED';
    }
}
