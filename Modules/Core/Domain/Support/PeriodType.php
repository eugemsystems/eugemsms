<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

/**
 * Academic and financial states are independent state machines on the same
 * term (Volume 1 Part 4.3). A session-bound model declares which track
 * governs its writes — see `BelongsToSession` and `HasPeriodType`.
 */
enum PeriodType: string
{
    case Academic = 'academic';
    case Financial = 'financial';
}
