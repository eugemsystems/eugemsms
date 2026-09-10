<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

/**
 * The period state machine shared by the academic and financial tracks of
 * a term/academic year (Volume 1 Part 4.3, Book A CORE-03 §3).
 */
enum PeriodState: string
{
    case Planned = 'planned';
    case Open = 'open';
    case SoftClosed = 'soft_closed';
    case Locked = 'locked';
    case Archived = 'archived';

    public function isWritable(): bool
    {
        return $this === self::Open;
    }

    public function isWritableWithOverride(): bool
    {
        return $this === self::SoftClosed;
    }
}
